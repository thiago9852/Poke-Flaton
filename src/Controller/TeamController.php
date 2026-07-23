<?php

namespace App\Controller;

use App\Entity\Moveset;
use App\Service\PokeApiService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TeamController extends AbstractController
{
    private PokeApiService $pokeApiService;

    public function __construct(PokeApiService $pokeApiService)
    {
        $this->pokeApiService = $pokeApiService;
    }

    #[Route('/team', name: 'app_team_builder')]
    public function index(): Response
    {
        $rolesList = [
            'Dano Bruto (Físico)',
            'Dano Bruto (Especial)',
            'Suporte de Clima/Terreno',
            'Tank',
            'Utilitário',
            'Debuffer',
        ];

        $allNatures = $this->pokeApiService->getNatures();
        $naturesMap = [];
        foreach ($allNatures as $n) {
            $naturesMap[$n['name']] = $n;
        }
        ksort($naturesMap);

        return $this->render('team/index.html.twig', [
            'rolesList' => $rolesList,
            'naturesMap' => $naturesMap,
        ]);
    }

    #[Route('/api/team/pokemon-data/{name}', name: 'api_team_pokemon_data', methods: ['GET'])]
    public function getPokemonData(string $name, EntityManagerInterface $entityManager): JsonResponse
    {
        $nameLower = strtolower(trim($name));
        $pokemon = $this->pokeApiService->getPokemonDetails($nameLower);

        if (!$pokemon) {
            return new JsonResponse(['error' => 'Pokémon não encontrado'], 404);
        }

        // Busca o moveset padrão/mais recente no banco de dados para este Pokémon
        $movesets = $entityManager->getRepository(Moveset::class)->findBy(
            ['pokemonName' => $pokemon['name']],
            ['isDefault' => 'DESC', 'createdAt' => 'DESC']
        );

        $defaultMoveset = null;
        if (!empty($movesets)) {
            $defaultMoveset = $movesets[0];
        }

        // Carrega Golpes Base (Por Nível) do scratch/default_base_moves.json
        $baseMovesList = [];
        $defaultBaseMovesPath = $this->getParameter('kernel.project_dir') . '/scratch/default_base_moves.json';
        if (file_exists($defaultBaseMovesPath)) {
            $defaultBaseMovesData = json_decode(file_get_contents($defaultBaseMovesPath), true) ?: [];
            if (isset($defaultBaseMovesData[$nameLower])) {
                $baseMovesList = array_slice($defaultBaseMovesData[$nameLower], 0, 10);
            }
        }

        // Busca detalhes dos Golpes Base por Nível
        $baseMovesDetailed = [];
        foreach ($baseMovesList as $idx => $mName) {
            $mSlug = preg_replace('/-+/', '-', str_replace(' ', '-', strtolower(trim($mName))));
            $md = $this->pokeApiService->getMoveDetails($mSlug);
            $baseMovesDetailed[] = [
                'index' => $idx + 1,
                'slug' => $mSlug,
                'name' => ucwords(str_replace('-', ' ', $mSlug)),
                'type' => $md['type'] ?? 'normal',
                'category' => $md['category'] ?? 'status',
                'power' => $md['power'] ?? '—',
                'accuracy' => $md['accuracy'] ? $md['accuracy'] . '%' : '—',
                'description' => $md['description'] ?? 'Efeito do golpe.',
                'type_icon' => $md['type_icon'] ?? '',
            ];
        }

        $nature = $defaultMoveset ? $defaultMoveset->getNature() : null;
        $heldItem = $defaultMoveset ? $defaultMoveset->getHeldItem() : null;
        $ability = $defaultMoveset ? $defaultMoveset->getAbility() : null;

        if (!$ability && !empty($pokemon['abilities'])) {
            $ability = $pokemon['abilities'][0]['name'] ?? null;
        }

        // Formata todos os movesets criados no banco para este Pokémon
        $formattedMovesets = [];
        foreach ($movesets as $ms) {
            $msMoves = [];
            foreach ($ms->getMoves() as $mName) {
                if (empty($mName)) continue;
                $mSlug = preg_replace('/-+/', '-', str_replace(' ', '-', strtolower(trim($mName))));
                $md = $this->pokeApiService->getMoveDetails($mSlug);
                $msMoves[] = [
                    'index' => count($msMoves) + 1,
                    'slug' => $mSlug,
                    'name' => ucwords(str_replace('-', ' ', $mSlug)),
                    'type' => $md['type'] ?? 'normal',
                    'category' => $md['category'] ?? 'status',
                    'power' => $md['power'] ?? '—',
                    'accuracy' => $md['accuracy'] ? $md['accuracy'] . '%' : '—',
                    'description' => $md['description'] ?? 'Efeito do golpe.',
                    'type_icon' => $md['type_icon'] ?? '',
                ];
            }

            $typeLabel = match($ms->getType()) {
                'pvp' => 'PvP',
                'dg' => 'Dungeon (DG)',
                default => 'Padrão',
            };

            $authorLabel = ($ms->getAuthor() && $ms->getAuthor() !== 'Anônimo') ? $ms->getAuthor() : 'Anônimo';
            $title = sprintf('[%s] %s (por %s)', $typeLabel, $ms->isDefault() ? 'Moveset Oficial' : 'Moveset Criado', $authorLabel);

            $formattedMovesets[] = [
                'id' => $ms->getId(),
                'title' => $title,
                'type' => $ms->getType(),
                'isDefault' => $ms->isDefault(),
                'nature' => $ms->getNature() ? strtolower($ms->getNature()) : '',
                'heldItem' => $ms->getHeldItem() ? strtolower($ms->getHeldItem()) : '',
                'ability' => $ms->getAbility() ? strtolower($ms->getAbility()) : '',
                'moves' => $msMoves,
            ];
        }

        return new JsonResponse([
            'pokemon' => [
                'id' => $pokemon['id'],
                'species_id' => $pokemon['species_id'],
                'name' => $pokemon['name'],
                'display_name' => ucwords(str_replace(['-mega', '-x', '-y', '-z', '-'], [' Mega', ' X', ' Y', ' Z', ' '], $pokemon['name'])),
                'sprite' => $pokemon['sprite_official'],
                'types' => $pokemon['types'],
            ],
            'baseMoves' => $baseMovesDetailed,
            'moves' => $baseMovesDetailed,
            'movesets' => $formattedMovesets,
            'nature' => $nature ? strtolower($nature) : '',
            'heldItem' => $heldItem ? strtolower($heldItem) : '',
            'ability' => $ability ? strtolower($ability) : '',
        ]);
    }
}
