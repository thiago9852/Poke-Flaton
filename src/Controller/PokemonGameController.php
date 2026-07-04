<?php

namespace App\Controller;

use App\Entity\PokemonGameScore;
use App\Entity\PokemonAccess;
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

    #[Route('/pokemon-do-dia/info', name: 'app_game_daily_info')]
    public function info(): Response
    {
        $allowedList = $this->getAllowedBasePokemonList();

        // 1. Obter palpites digitados pelos usuários nos últimos 30 dias (PokemonAccess com prefixo 'guess-')
        $accessRepo = $this->entityManager->getRepository(PokemonAccess::class);
        $thirtyDaysAgo = new \DateTime('-30 days');

        $allGuesses = $accessRepo->createQueryBuilder('a')
            ->where('a.pokemonName LIKE :prefix')
            ->andWhere('a.lastAccessedAt >= :thirtyDaysAgo')
            ->setParameter('prefix', 'guess-%')
            ->setParameter('thirtyDaysAgo', $thirtyDaysAgo)
            ->orderBy('a.views', 'DESC')
            ->getQuery()
            ->getResult();

        // --- 1. TOP 10 POKÉMONS MAIS LEMBRADOS (DIGITADOS) ---
        $topGuesses = array_slice($allGuesses, 0, 10);
        $topGuessesNames = array_map(fn($g) => substr($g->getPokemonName(), 6), $topGuesses);
        $topGuessesDetails = [];
        if (!empty($topGuessesNames)) {
            $topGuessesDetails = $this->pokeApiService->getPokemonDetailsBatchByNames($topGuessesNames);
        }

        $topPokemons = [];
        foreach ($topGuesses as $guess) {
            $pName = substr($guess->getPokemonName(), 6);
            $nameLower = strtolower($pName);
            if (isset($topGuessesDetails[$nameLower])) {
                $p = $topGuessesDetails[$nameLower];
                $topPokemons[] = [
                    'id' => $p['id'],
                    'name' => $p['name'],
                    'display_name' => $p['default_display_name'] ?? ucfirst(str_replace('-', ' ', $p['name'])),
                    'sprite' => $p['sprite_official'],
                    'plays' => $guess->getViews(), // número de vezes que foi digitado
                    'wins' => 0,
                    'types' => $p['types']
                ];
            }
        }

        // --- 2. GERAÇÕES DOS POKÉMONS DIGITADOS ---
        $genViews = array_fill(1, 9, 0);
        foreach ($allGuesses as $guess) {
            $gen = PokeApiService::getGenerationById($guess->getPokemonId());
            if ($gen >= 1 && $gen <= 9) {
                $genViews[$gen] += $guess->getViews();
            }
        }
        arsort($genViews);

        $allGenerationsMetadata = [
            1 => ['number' => 1, 'games' => 'Red & Blue', 'region' => 'Kanto'],
            2 => ['number' => 2, 'games' => 'Gold & Silver', 'region' => 'Johto'],
            3 => ['number' => 3, 'games' => 'Ruby & Sapphire', 'region' => 'Hoenn'],
            4 => ['number' => 4, 'games' => 'Diamond & Pearl', 'region' => 'Sinnoh'],
            5 => ['number' => 5, 'games' => 'Black & White', 'region' => 'Unova'],
            6 => ['number' => 6, 'games' => 'X & Y', 'region' => 'Kalos'],
            7 => ['number' => 7, 'games' => 'Sun & Moon', 'region' => 'Alola'],
            8 => ['number' => 8, 'games' => 'Sword & Shield', 'region' => 'Galar'],
            9 => ['number' => 9, 'games' => 'Scarlet & Violet', 'region' => 'Paldea']
        ];

        $generationRanking = [];
        $maxGenViews = !empty($genViews) ? max($genViews) : 1;
        foreach ($genViews as $genNumber => $views) {
            if ($views > 0) {
                $generationRanking[] = [
                    'generation' => $genNumber,
                    'views' => $views,
                    'region' => $allGenerationsMetadata[$genNumber]['region'] ?? 'Desconhecida',
                    'games' => $allGenerationsMetadata[$genNumber]['games'] ?? '',
                    'percentage' => round(($views / $maxGenViews) * 100)
                ];
            }
        }

        // --- 3. TIPOS DOS POKÉMONS DIGITADOS ---
        $guessNames30Days = array_map(fn($g) => substr($g->getPokemonName(), 6), $allGuesses);
        $detailsMap30Days = [];
        if (!empty($guessNames30Days)) {
            $detailsMap30Days = $this->pokeApiService->getPokemonDetailsBatchByNames($guessNames30Days);
        }

        $typeViews = [];
        foreach ($allGuesses as $guess) {
            $pName = substr($guess->getPokemonName(), 6);
            $nameLower = strtolower($pName);
            if (isset($detailsMap30Days[$nameLower])) {
                foreach ($detailsMap30Days[$nameLower]['types'] as $type) {
                    $type = strtolower($type);
                    if (!isset($typeViews[$type])) {
                        $typeViews[$type] = 0;
                    }
                    $typeViews[$type] += $guess->getViews();
                }
            }
        }
        arsort($typeViews);

        $typeRanking = [];
        $maxTypeViews = !empty($typeViews) ? max($typeViews) : 1;
        foreach ($typeViews as $type => $views) {
            $typeRanking[] = [
                'type' => $type,
                'views' => $views,
                'percentage' => round(($views / $maxTypeViews) * 100)
            ];
        }

        // --- 4. POKÉMONS DO DIA COM MAIS PRESENÇA POR GERAÇÃO (A PARTIR DO HISTÓRICO DO JOGO) ---
        $repo = $this->entityManager->getRepository(PokemonGameScore::class);
        $allScores = $repo->findAll();
        $basicList = $this->pokeApiService->getPokemonGameList();
        usort($basicList, fn($a, $b) => $a['id'] <=> $b['id']);
        $listCount = count($basicList);

        $secretStats = []; // name => ['id' => x, 'name' => y, 'display_name' => z, 'plays' => cnt]
        if ($listCount > 0) {
            foreach ($allScores as $score) {
                $user = $score->getUser();
                if ($user) {
                    $userSeed = 'user-' . $user->getUserIdentifier();
                } else {
                    $userToken = $score->getUserToken();
                    $userSeed = 'anon-' . $userToken;
                }

                $dateStr = $score->getGameDate() ? $score->getGameDate()->format('Y-m-d') : null;
                if (!$dateStr) {
                    continue;
                }

                $hash = md5('pokeflaton-secret-daily-salt-' . $dateStr . '-' . $userSeed);
                $seed = hexdec(substr($hash, 0, 8));
                $targetIndex = $seed % $listCount;
                $target = $basicList[$targetIndex];
                $pName = $target['name'];

                if (!isset($secretStats[$pName])) {
                    $secretStats[$pName] = [
                        'id' => $target['id'],
                        'name' => $pName,
                        'display_name' => $target['display_name'] ?? ucfirst(str_replace('-', ' ', $pName)),
                        'plays' => 0
                    ];
                }
                $secretStats[$pName]['plays']++;
            }
        }

        $genTopPokemons = [];
        for ($g = 1; $g <= 9; $g++) {
            $genTopPokemons[$g] = [];
        }
        foreach ($secretStats as $stat) {
            $gen = PokeApiService::getGenerationById($stat['id']);
            if ($gen >= 1 && $gen <= 9) {
                $genTopPokemons[$gen][] = $stat;
            }
        }

        for ($g = 1; $g <= 9; $g++) {
            uasort($genTopPokemons[$g], fn($a, $b) => $b['plays'] <=> $a['plays']);
            $genTopPokemons[$g] = array_slice($genTopPokemons[$g], 0, 10);
        }

        // --- 5. TAXA DE ACERTO POR DIA ---
        $dailySuccessRates = $repo->findDailySuccessRates(10);

        // --- 6. CÁLCULO DE MÉDIAS GLOBAIS ---
        $totalGames = (int) $repo->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $totalWins = (int) $repo->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.won = :won')
            ->setParameter('won', true)
            ->getQuery()
            ->getSingleScalarResult();

        $avgAttemptsVal = $repo->createQueryBuilder('s')
            ->select('AVG(s.attempts)')
            ->where('s.won = :won')
            ->setParameter('won', true)
            ->getQuery()
            ->getSingleScalarResult();

        $avgAttempts = $avgAttemptsVal !== null ? round((float) $avgAttemptsVal, 1) : 0;
        $winRate = $totalGames > 0 ? round(($totalWins / $totalGames) * 100, 1) : 0;

        // --- PREPARAR DADOS DE ESTATÍSTICA PARA OS GRÁFICOS (JSON) ---
        $chartTopPokemons = [];
        foreach (array_slice($topPokemons, 0, 10) as $stat) {
            $chartTopPokemons[] = [
                'name' => $stat['display_name'],
                'plays' => $stat['plays']
            ];
        }

        $chartGenerations = [];
        foreach ($generationRanking as $gen) {
            $chartGenerations[] = [
                'label' => 'Geração ' . $gen['generation'] . ' (' . $gen['region'] . ')',
                'value' => $gen['views']
            ];
        }

        $chartTypes = [];
        foreach (array_slice($typeRanking, 0, 10) as $t) {
            $chartTypes[] = [
                'label' => ucfirst($t['type']),
                'value' => $t['views']
            ];
        }

        $chartGenTopPokemons = [];
        for ($i = 0; $i < 10; $i++) {
            $dataPoints = [];
            for ($g = 1; $g <= 9; $g++) {
                $pokemon = $genTopPokemons[$g][$i] ?? null;
                if ($pokemon) {
                    $dataPoints[] = [
                        'x' => 'Geração ' . $g,
                        'y' => $pokemon['plays'],
                        'name' => $pokemon['display_name']
                    ];
                } else {
                    $dataPoints[] = [
                        'x' => 'Geração ' . $g,
                        'y' => 0,
                        'name' => 'Nenhum'
                    ];
                }
            }
            $chartGenTopPokemons[] = [
                'label' => 'Rank ' . ($i + 1),
                'data' => $dataPoints
            ];
        }

        $chartDailySuccessRates = [];
        foreach (array_reverse($dailySuccessRates) as $day) {
            $chartDailySuccessRates[] = [
                'date' => date('d/m', strtotime($day['date'])),
                'rate' => $day['rate'],
                'total' => $day['total'],
                'won' => $day['won']
            ];
        }

        return $this->render('game/info.html.twig', [
            'title' => 'Estatísticas Globais | Pokémon do Dia',
            'totalGames' => $totalGames,
            'winRate' => $winRate,
            'avgAttempts' => $avgAttempts,
            'topPokemons' => $topPokemons,
            'generationRanking' => $generationRanking,
            'typeRanking' => $typeRanking,
            'dailySuccessRates' => $dailySuccessRates,
            // JSON strings para os gráficos
            'chartTopPokemonsJson' => json_encode($chartTopPokemons),
            'chartGenerationsJson' => json_encode($chartGenerations),
            'chartTypesJson' => json_encode($chartTypes),
            'chartGenTopPokemonsJson' => json_encode($chartGenTopPokemons),
            'chartDailySuccessRatesJson' => json_encode($chartDailySuccessRates)
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

        // Registrar o palpite em PokemonAccess com prefixo 'guess-'
        try {
            $accessRepo = $this->entityManager->getRepository(PokemonAccess::class);
            $guessKey = 'guess-' . strtolower($guessPokemon['name']);
            $pokemonAccess = $accessRepo->findOneBy(['pokemonName' => $guessKey]);
            if (!$pokemonAccess) {
                $pokemonAccess = new PokemonAccess();
                $pokemonAccess->setPokemonName($guessKey);
                $pokemonAccess->setPokemonId($guessPokemon['id']);
                $pokemonAccess->setViews(0);
            }
            $pokemonAccess->incrementViews();
            $pokemonAccess->setLastAccessedAt(new \DateTime());
            $this->entityManager->persist($pokemonAccess);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            // Ignore database logging errors
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
