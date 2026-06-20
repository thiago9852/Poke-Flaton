<?php

namespace App\Controller;

use App\Entity\PokemonGameScore;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\PokeApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class PokemonGameController extends AbstractController
{
    private PokeApiService $pokeApiService;
    private EntityManagerInterface $entityManager;

    public function __construct(PokeApiService $pokeApiService, EntityManagerInterface $entityManager)
    {
        $this->pokeApiService = $pokeApiService;
        $this->entityManager = $entityManager;
    }

    #[Route('/pokemon-do-dia', name: 'app_game_daily')]
    public function daily(): Response
    {
        $allowedList = $this->getAllowedBasePokemonList();

        $startOfMonth = new \DateTime('first day of this month 00:00:00');
        $endOfMonth = new \DateTime('last day of this month 23:59:59');

        $repo = $this->entityManager->getRepository(PokemonGameScore::class);
        $monthlyRanking = $repo->findMonthlyTop5($startOfMonth, $endOfMonth);
        
        return $this->render('game/index.html.twig', [
            'mode' => 'daily',
            'title' => 'Pokémon do Dia',
            'pokemonListJson' => json_encode($allowedList),
            'monthlyRanking' => $monthlyRanking
        ]);
    }



    #[Route('/api/game/guess', name: 'api_game_guess', methods: ['POST'])]
    public function guess(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $guessName = strtolower(trim($data['guess'] ?? ''));

        if (empty($guessName)) {
            return new JsonResponse(['error' => 'Por favor, informe o nome de um Pokémon.'], 400);
        }

        // Obtém a lista de permitidos
        $allowedList = $this->getAllowedBasePokemonList();
        $allowedNames = array_map(fn($p) => $p['name'], $allowedList);

        if (!in_array($guessName, $allowedNames)) {
            return new JsonResponse(['error' => 'Pokémon não permitido ou inválido nesta geração.'], 400);
        }

        // Determina o Pokémon secreto (Target)
        try {
            $targetPokemon = $this->getTargetPokemon($data, $request);
            $guessPokemon = $this->pokeApiService->getPokemonDetails($guessName);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Erro ao carregar detalhes do Pokémon.'], 500);
        }

        // Comparações
        $isCorrect = ($guessPokemon['id'] === $targetPokemon['id']);

        // Geração
        $guessGen = PokeApiService::getGenerationById($guessPokemon['id']);
        $targetGen = PokeApiService::getGenerationById($targetPokemon['id']);
        $genStatus = 'correct';
        if ($guessGen < $targetGen) {
            $genStatus = 'higher';
        } elseif ($guessGen > $targetGen) {
            $genStatus = 'lower';
        }

        // Peso (convertendo hectogramas para kg)
        $guessWeight = $guessPokemon['weight'] / 10;
        $targetWeight = $targetPokemon['weight'] / 10;
        $weightStatus = 'correct';
        if ($guessWeight < $targetWeight) {
            $weightStatus = 'higher';
        } elseif ($guessWeight > $targetWeight) {
            $weightStatus = 'lower';
        }

        // Altura (convertendo decímetros para metros)
        $guessHeight = $guessPokemon['height'] / 10;
        $targetHeight = $targetPokemon['height'] / 10;
        $heightStatus = 'correct';
        if ($guessHeight < $targetHeight) {
            $heightStatus = 'higher';
        } elseif ($guessHeight > $targetHeight) {
            $heightStatus = 'lower';
        }

        // Tipos
        $guessTypes = $guessPokemon['types'];
        $targetTypes = $targetPokemon['types'];
        
        // Se o pokémon tiver apenas 1 tipo, duplica para preencher a segunda coluna
        if (count($guessTypes) === 1) {
            $guessTypes[1] = $guessTypes[0];
        }
        if (count($targetTypes) === 1) {
            $targetTypes[1] = $targetTypes[0];
        }

        $typeFeedback = [];
        foreach ($guessTypes as $index => $gType) {
            $status = 'incorrect';
            if (isset($targetTypes[$index]) && $targetTypes[$index] === $gType) {
                $status = 'correct';
            } elseif (in_array($gType, $targetTypes)) {
                $status = 'partial';
            }
            $typeFeedback[] = [
                'type' => $gType,
                'status' => $status
            ];
        }

        // Estrutura de resposta
        $response = [
            'is_correct' => $isCorrect,
            'guess' => [
                'name' => ucfirst(str_replace('-', ' ', $guessPokemon['name'])),
                'sprite' => $guessPokemon['sprite_official'],
                'generation' => [
                    'value' => $guessGen,
                    'status' => $genStatus
                ],
                'types' => $typeFeedback,
                'weight' => [
                    'value' => $guessWeight,
                    'status' => $weightStatus
                ],
                'height' => [
                    'value' => $guessHeight,
                    'status' => $heightStatus
                ]
            ]
        ];

        // Se acertou, revela informações completas do secreto
        if ($isCorrect) {
            $response['secret'] = [
                'id' => $targetPokemon['id'],
                'name' => ucfirst(str_replace('-', ' ', $targetPokemon['name'])),
                'sprite' => $targetPokemon['sprite_official'],
                'description' => $targetPokemon['description'],
                'types' => $targetPokemon['types'],
                'weight' => $targetWeight,
                'height' => $targetHeight,
                'stats' => $targetPokemon['stats']
            ];

            // Salva score de vitória no banco
            $userToken = $data['user_token'] ?? null;
            $attempts = (int) ($data['attempts'] ?? 1);
            $this->saveScore($request, $userToken, $attempts, true);

            // Se o usuário estiver logado, registra na pokedex com a data atual
            $user = $this->getUser();
            if ($user) {
                $caught = $user->getCaughtPokemon();
                $pkmName = strtolower($targetPokemon['name']);
                
                $isAlreadyCaught = false;
                foreach ($caught as $key => $val) {
                    $caughtName = is_int($key) ? $val : $key;
                    if (strtolower($caughtName) === $pkmName) {
                        $isAlreadyCaught = true;
                        break;
                    }
                }
                
                if (!$isAlreadyCaught) {
                    $caught[$pkmName] = date('Y-m-d H:i:s');
                    $user->setCaughtPokemon($caught);
                    $this->entityManager->persist($user);
                    $this->entityManager->flush();
                }
            }
        }

        return new JsonResponse($response);
    }

    #[Route('/api/game/reveal', name: 'api_game_reveal', methods: ['POST'])]
    public function reveal(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        try {
            $targetPokemon = $this->getTargetPokemon($data, $request);
            
            // Salva score de derrota/desistência no banco
            $userToken = $data['user_token'] ?? null;
            $attempts = (int) ($data['attempts'] ?? 0);
            $this->saveScore($request, $userToken, $attempts, false);

            return new JsonResponse([
                'id' => $targetPokemon['id'],
                'name' => ucfirst(str_replace('-', ' ', $targetPokemon['name'])),
                'sprite' => $targetPokemon['sprite_official'],
                'description' => $targetPokemon['description'],
                'types' => $targetPokemon['types'],
                'weight' => $targetPokemon['weight'] / 10,
                'height' => $targetPokemon['height'] / 10,
                'stats' => $targetPokemon['stats']
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Erro ao revelar Pokémon secreto.'], 500);
        }
    }



    /**
     * Retorna a lista básica de pokémons permitidos estruturada para o autocomplete
     */
    private function getAllowedBasePokemonList(): array
    {
        $basicList = $this->pokeApiService->getPokemonGameList();
        
        $formatted = [];
        foreach ($basicList as $pokemon) {
            $formatted[] = [
                'id' => $pokemon['id'],
                'name' => $pokemon['name'],
                'display_name' => ucfirst(str_replace('-', ' ', $pokemon['name'])),
                'sprite' => $pokemon['sprite']
            ];
        }

        // Ordena por nome alfabeticamente para facilitar navegação
        usort($formatted, fn($a, $b) => strcmp($a['name'], $b['name']));

        return $formatted;
    }

    /**
     * Resolve o Pokémon secreto para a rodada
     */
    private function getTargetPokemon(array $data, Request $request): array
    {
        $basicList = $this->pokeApiService->getPokemonGameList();
        // Ordena por ID deterministicamente
        usort($basicList, fn($a, $b) => $a['id'] <=> $b['id']);

        $dateStr = date('Y-m-d');
        $user = $this->getUser();
        
        if ($user) {
            $userSeed = 'user-' . $user->getUserIdentifier();
        } else {
            $userToken = $data['user_token'] ?? null;
            if (!$userToken) {
                $session = $request->getSession();
                if (!$session->isStarted()) {
                    $session->start();
                }
                $userToken = $session->getId();
            }
            $userSeed = 'anon-' . $userToken;
        }

        $hash = md5('pokeflaton-secret-daily-salt-' . $dateStr . '-' . $userSeed);
        $seed = hexdec(substr($hash, 0, 8));
        $targetIndex = $seed % count($basicList);
        $targetName = $basicList[$targetIndex]['name'];

        return $this->pokeApiService->getPokemonDetails($targetName);
    }

    private function saveScore(Request $request, ?string $userToken, int $attempts, bool $won): void
    {
        $user = $this->getUser();

        if (!$user && !$userToken) {
            return;
        }

        $dateStr = date('Y-m-d');
        $gameDate = new \DateTime($dateStr);

        $repo = $this->entityManager->getRepository(PokemonGameScore::class);
        $criteria = ['gameDate' => $gameDate];
        if ($user) {
            $criteria['user'] = $user;
        } else {
            $criteria['userToken'] = $userToken;
        }

        $existing = $repo->findOneBy($criteria);
        if (!$existing) {
            $score = new PokemonGameScore();
            $score->setUser($user);
            $score->setUserToken($userToken);
            $score->setAttempts($attempts);
            $score->setWon($won);
            $score->setGameDate($gameDate);
            $score->setCreatedAt(new \DateTime());

            if ($user) {
                $displayName = $user->getApelido() ?: $user->getUsername();
            } else {
                $displayName = 'Treinador Anônimo';
            }
            $score->setUsername($displayName);

            $this->entityManager->persist($score);
            $this->entityManager->flush();
        }
    }

}
