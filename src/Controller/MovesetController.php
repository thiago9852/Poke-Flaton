<?php

namespace App\Controller;

use App\Entity\Moveset;
use App\Repository\MovesetRepository;
use App\Service\PokeApiService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MovesetController extends AbstractController
{
    private PokeApiService $pokeApiService;
    private EntityManagerInterface $entityManager;

    public function __construct(PokeApiService $pokeApiService, EntityManagerInterface $entityManager)
    {
        $this->pokeApiService = $pokeApiService;
        $this->entityManager = $entityManager;
    }

    #[Route('/pokemon/{name}/moveset/new', name: 'app_moveset_new', methods: ['GET', 'POST'])]
    public function new(string $name, Request $request): Response
    {
        try {
            $pokemon = $this->pokeApiService->getPokemonDetails($name);
            if (!$this->pokeApiService->isPokemonAllowed($pokemon['id'])) {
                throw $this->createNotFoundException('Pokémon não encontrado.');
            }
        } catch (\Exception $e) {
            throw $this->createNotFoundException('Pokémon não encontrado.');
        }

        // Carregar Natures e Itens para preencher os selects do formulário
        $natures = $this->pokeApiService->getNatures();
        $items   = $this->pokeApiService->getItems();

        // Calcular o limite de moves com base no estágio evolutivo e BST
        $maxMoves = $this->pokeApiService->calculateMaxMoves($pokemon['name'], $pokemon['stats']);

        $errors = [];

        if ($request->isMethod('POST')) {
            $type = $request->request->get('type');
            $ability = $request->request->get('ability');
            $heldItem = $request->request->get('heldItem');
            $nature = $request->request->get('nature');

            // Capturar até 10 golpes e filtrar vazios
            $moves = [];
            for ($i = 1; $i <= 10; $i++) {
                $moveVal = $request->request->get('move' . $i);
                if (!empty($moveVal)) {
                    $moves[] = $moveVal;
                }
            }

            // Validações básicas
            if (empty($type) || !in_array($type, ['padrao', 'pvp'])) {
                $errors[] = 'Tipo de moveset inválido.';
            }
            if (count($moves) < 4 || count($moves) > $maxMoves) {
                $errors[] = sprintf('Você deve selecionar entre 4 e %d golpes.', $maxMoves);
            }
            if (empty($ability)) {
                $errors[] = 'A habilidade recomendada deve ser selecionada.';
            }
            if (empty($heldItem)) {
                $errors[] = 'O item recomendado deve ser selecionado.';
            }
            if (empty($nature)) {
                $errors[] = 'A Nature ideal deve ser selecionada.';
            }

            if (empty($errors)) {
                $moveset = new Moveset();
                $moveset->setPokemonName($pokemon['name']);
                $moveset->setPokemonId($pokemon['id']);
                $moveset->setType($type);
                $moveset->setMoves($moves);
                $moveset->setAbility($ability);
                $moveset->setHeldItem($heldItem);
                $moveset->setNature($nature);
                $moveset->setAuthor('Anônimo'); // Se não tiver sessão, ficará como anônimo
                $this->entityManager->persist($moveset);

                // Add sugestão
                $this->addSuggestionVote($pokemon['name'], 'nature', $nature);
                $this->addSuggestionVote($pokemon['name'], 'ability', $ability);
                $this->addSuggestionVote($pokemon['name'], 'item', $heldItem);

                $this->entityManager->flush();

                $this->addFlash('success', 'Moveset criado com sucesso!');

                return $this->redirectToRoute('app_pokemon_detail', ['name' => $pokemon['name']]);
            }
        }

        return $this->render('moveset/new.html.twig', [
            'pokemon' => $pokemon,
            'natures' => $natures,
            'items' => $items,
            'maxMoves' => $maxMoves,
            'errors' => $errors,
        ]);
    }

    private function addSuggestionVote(string $pokemonName, string $type, string $value): void
    {
        $repo = $this->entityManager->getRepository(\App\Entity\PokemonSuggestion::class);
        $suggestion = $repo->findOneBy([
            'pokemonName' => $pokemonName,
            'type' => $type,
            'value' => $value,
        ]);

        if (!$suggestion) {
            $suggestion = new \App\Entity\PokemonSuggestion();
            $suggestion->setPokemonName($pokemonName);
            $suggestion->setType($type);
            $suggestion->setValue($value);
            $suggestion->setVotes(0);
            $this->entityManager->persist($suggestion);
        }

        $suggestion->incrementVotes(1);
    }

    #[Route('/moveset/{id}/vote', name: 'app_moveset_vote', methods: ['POST'])]
    public function vote(int $id, MovesetRepository $movesetRepository): JsonResponse
    {
        $moveset = $movesetRepository->find($id);

        if (!$moveset) {
            return new JsonResponse(['error' => 'Moveset não encontrado.'], Response::HTTP_NOT_FOUND);
        }

        $moveset->incrementVotes();
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'votes' => $moveset->getVotes()
        ]);
    }
}
