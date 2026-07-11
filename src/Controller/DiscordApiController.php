<?php

namespace App\Controller;

use App\Entity\Moveset;
use App\Entity\PokemonLocation;
use App\Repository\MovesetRepository;
use App\Service\PokeApiService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class DiscordApiController extends AbstractController
{
    private PokeApiService $pokeApiService;
    private EntityManagerInterface $entityManager;

    public function __construct(PokeApiService $pokeApiService, EntityManagerInterface $entityManager)
    {
        $this->pokeApiService = $pokeApiService;
        $this->entityManager = $entityManager;
    }

    #[Route('/api/discord/pokemon/{name}', name: 'api_discord_pokemon', methods: ['GET'])]
    public function pokemonInfo(string $name): JsonResponse
    {
        try {
            $pokemon = $this->pokeApiService->getPokemonDetails($name);
            $isAllowed = $this->pokeApiService->isPokemonAllowed($pokemon['id']);
            if (!$isAllowed && $pokemon['id'] >= 10000 && isset($pokemon['species_id'])) {
                $isAllowed = $this->pokeApiService->isPokemonAllowed($pokemon['species_id']);
            }
            if (!$isAllowed) {
                return new JsonResponse(['error' => 'Pokémon não permitido ou não encontrado.'], Response::HTTP_NOT_FOUND);
            }
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Pokémon não encontrado: ' . $e->getMessage()], Response::HTTP_NOT_FOUND);
        }

        // Buscar locais aprovados do banco de dados
        $locationEntities = $this->entityManager->getRepository(PokemonLocation::class)->findBy(
            ['pokemonName' => $pokemon['name'], 'isApproved' => true],
            ['createdAt' => 'ASC']
        );
        $locations = array_map(fn($loc) => $loc->getLocationName(), $locationEntities);

        // Buscar cadeia evolutiva
        $evolutionChain = [];
        try {
            $rawChain = $this->pokeApiService->getPokemonEvolutionChain($pokemon['species_name'], $pokemon);
            foreach ($rawChain as $stage => $nodes) {
                $stageNodes = [];
                foreach ($nodes as $node) {
                    $stageNodes[] = [
                        'name' => $node['name'] ?? '',
                        'id' => $node['id'] ?? 0,
                        'evolution_method' => $node['evolution_method'] ?? null,
                        'evolution_gender' => $node['evolution_gender'] ?? null
                    ];
                }
                $evolutionChain[$stage] = $stageNodes;
            }
        } catch (\Exception $e) {
            // Ignorar erro e enviar vazio
        }

        // Buscar a nature recomendada baseada nos movesets cadastrados
        $movesetRepo = $this->entityManager->getRepository(Moveset::class);
        $movesets = $movesetRepo->findBy(['pokemonName' => $pokemon['name']]);
        $recommendedNature = 'Nenhuma cadastrada';
        
        $natureCounts = [];
        foreach ($movesets as $m) {
            $n = $m->getNature();
            if (!empty($n)) {
                $natureCounts[$n] = ($natureCounts[$n] ?? 0) + 1;
            }
        }
        if (!empty($natureCounts)) {
            arsort($natureCounts);
            $mostUsedNature = array_key_first($natureCounts);
            $recommendedNature = ucfirst($mostUsedNature);
        }

        return new JsonResponse([
            'id' => $pokemon['id'],
            'name' => $pokemon['name'],
            'species_id' => $pokemon['species_id'],
            'sprite' => $pokemon['sprite_official'],
            'types' => $pokemon['types'],
            'stats' => $pokemon['stats'],
            'locations' => $locations,
            'evolution_chain' => $evolutionChain,
            'recommended_nature' => $recommendedNature
        ]);
    }

    #[Route('/api/discord/nature/{name}', name: 'api_discord_nature', methods: ['GET'])]
    public function natureInfo(string $name): JsonResponse
    {
        $nameLower = strtolower(trim($name));
        
        // Dicionário de traduções de Natures de PT-BR para Inglês (caso o usuário digite em português)
        $natureTranslations = [
            'audaz' => 'hardy',
            'docil' => 'docile',
            'dócil' => 'docile',
            'audaz' => 'brave',
            'ardente' => 'fiery', 
            'hardy' => 'hardy', 'lonely' => 'lonely', 'brave' => 'brave', 'adamant' => 'adamant', 'naughty' => 'naughty',
            'bold' => 'bold', 'docile' => 'docile', 'relaxed' => 'relaxed', 'impish' => 'impish', 'lax' => 'lax',
            'timid' => 'timid', 'hasty' => 'hasty', 'serious' => 'serious', 'jolly' => 'jolly', 'naive' => 'naive',
            'modest' => 'modest', 'mild' => 'mild', 'quiet' => 'quiet', 'bashful' => 'bashful', 'rash' => 'rash',
            'calm' => 'calm', 'gentle' => 'gentle', 'sassy' => 'sassy', 'careful' => 'careful', 'quirky' => 'quirky',
            // PT-BR para EN
            'solitária' => 'lonely', 'solitaria' => 'lonely',
            'audaz' => 'brave',
            'firme' => 'adamant',
            'marota' => 'naughty',
            'ousada' => 'bold',
            'relaxada' => 'relaxed',
            'esperta' => 'impish',
            'ativa' => 'lax',
            'tímida' => 'timid', 'timida' => 'timid',
            'apressada' => 'hasty',
            'séria' => 'serious', 'seria' => 'serious',
            'alegre' => 'jolly',
            'ingênua' => 'naive', 'ingenua' => 'naive',
            'modesta' => 'modest',
            'mansa' => 'mild',
            'manso' => 'mild',
            'quieta' => 'quiet',
            'tímida' => 'bashful', 'vergonhosa' => 'bashful', // bashful
            'ardente' => 'rash', 'impetuosa' => 'rash', // rash
            'serena' => 'calm', 'calma' => 'calm',
            'dócil' => 'docile', 'docil' => 'docile',
            'gentil' => 'gentle',
            'atrevida' => 'sassy',
            'cauta' => 'careful', 'cuidadosa' => 'careful',
            'excêntrica' => 'quirky', 'excentrica' => 'quirky',
        ];

        $targetNature = $natureTranslations[$nameLower] ?? $nameLower;
        $natures = $this->pokeApiService->getNatures();

        $foundNature = null;
        foreach ($natures as $n) {
            if (strtolower($n['name']) === $targetNature) {
                $foundNature = $n;
                break;
            }
        }

        if (!$foundNature) {
            return new JsonResponse(['error' => 'Nature não encontrada.'], Response::HTTP_NOT_FOUND);
        }

        // Traduzir atributos
        $statTranslations = [
            'attack' => 'Ataque',
            'defense' => 'Defesa',
            'special-attack' => 'Ataque Especial',
            'special-defense' => 'Defesa Especial',
            'speed' => 'Velocidade',
            'hp' => 'HP',
            'none' => 'Nenhum (Neutro)'
        ];

        return new JsonResponse([
            'name' => ucfirst($foundNature['name']),
            'name_pt' => array_search($foundNature['name'], $natureTranslations) ?: ucfirst($foundNature['name']),
            'increased' => $foundNature['increased'],
            'decreased' => $foundNature['decreased'],
            'increased_pt' => $statTranslations[$foundNature['increased']] ?? $foundNature['increased'],
            'decreased_pt' => $statTranslations[$foundNature['decreased']] ?? $foundNature['decreased'],
        ]);
    }

    #[Route('/api/discord/moveset/{name}', name: 'api_discord_moveset', methods: ['GET'])]
    public function movesetInfo(string $name, MovesetRepository $movesetRepository): JsonResponse
    {
        try {
            $pokemon = $this->pokeApiService->getPokemonDetails($name);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Pokémon não encontrado.'], Response::HTTP_NOT_FOUND);
        }

        $movesets = $movesetRepository->findBy(
            ['pokemonName' => $pokemon['name']],
            ['isDefault' => 'DESC', 'votes' => 'DESC']
        );

        if (empty($movesets)) {
            return new JsonResponse(['error' => 'Nenhum moveset encontrado para este Pokémon.'], Response::HTTP_NOT_FOUND);
        }

        $moveset = $movesets[0]; // Melhor moveset (padrão ou mais votado)

        return new JsonResponse([
            'id' => $moveset->getId(),
            'pokemon_name' => $moveset->getPokemonName(),
            'type' => $moveset->getType(),
            'moves' => $moveset->getMoves(),
            'ability' => $moveset->getAbility(),
            'nature' => $moveset->getNature(),
            'held_item' => $moveset->getHeldItem(),
            'author' => $moveset->getAuthor(),
            'votes' => $moveset->getVotes(),
            'share_card_url' => $this->generateUrl(
                'app_moveset_share_card_only',
                ['id' => $moveset->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            )
        ]);
    }

    #[Route('/moveset/{id}/share-card-only', name: 'app_moveset_share_card_only', methods: ['GET'])]
    public function shareCardOnly(
        int $id,
        MovesetRepository $movesetRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $moveset = $movesetRepository->find($id);
        if (!$moveset) {
            throw $this->createNotFoundException('Moveset não encontrado.');
        }

        $pokemon = $this->pokeApiService->getPokemonDetails($moveset->getPokemonName());

        $moveDetails = [];
        foreach ($moveset->getMoves() as $moveName) {
            if (!empty($moveName) && !isset($moveDetails[$moveName])) {
                $moveDetails[$moveName] = $this->pokeApiService->getMoveDetails($moveName);
            }
        }

        $abilityDetails = [];
        $abilityName = $moveset->getAbility();
        if (!empty($abilityName)) {
            $abilityDetails[$abilityName] = $this->pokeApiService->getAbilityDetails($abilityName);
        }

        $itemDetails = [];
        $itemName = $moveset->getHeldItem();
        if (!empty($itemName)) {
            $itemDetails[$itemName] = $this->pokeApiService->getItemDetails($itemName);
        }

        $allNatures = $this->pokeApiService->getNatures();
        $naturesMap = [];
        foreach ($allNatures as $n) {
            $naturesMap[$n['name']] = $n;
        }

        // Buscar outros movesets para estatísticas de nature, se houver
        $siblingMovesets = $movesetRepository->findBy(['pokemonName' => $pokemon['name']]);
        
        $mostUsedNature = null;
        $mostUsedNaturePercent = 0;
        $totalMovesets = count($siblingMovesets);
        if ($totalMovesets > 0) {
            $overallNatureCounts = [];
            foreach ($siblingMovesets as $m) {
                $n = $m->getNature();
                if (!empty($n)) {
                    $overallNatureCounts[$n] = ($overallNatureCounts[$n] ?? 0) + 1;
                }
            }
            if (!empty($overallNatureCounts)) {
                arsort($overallNatureCounts);
                $mostUsedNature = array_key_first($overallNatureCounts);
                $mostUsedNaturePercent = (int) round(($overallNatureCounts[$mostUsedNature] / $totalMovesets) * 100);
            }
        }

        return $this->render('moveset/share_card_only.html.twig', [
            'm' => $moveset,
            'pokemon' => $pokemon,
            'moveDetails' => $moveDetails,
            'abilityDetails' => $abilityDetails,
            'itemDetails' => $itemDetails,
            'naturesMap' => $naturesMap,
            'mostUsedNature' => $mostUsedNature,
            'mostUsedNaturePercent' => $mostUsedNaturePercent,
        ]);
    }

    #[Route('/api/discord/ability/{name}', name: 'api_discord_ability', methods: ['GET'])]
    public function abilityInfo(string $name): JsonResponse
    {
        $nameClean = strtolower(trim($name));
        $nameKebab = str_replace(' ', '-', $nameClean);
        
        $ability = $this->pokeApiService->getAbilityDetails($nameKebab);
        
        if ($ability['description'] === 'Habilidade recomendada para ativar a estratégia.') {
            return new JsonResponse(['error' => 'Habilidade não encontrada.'], Response::HTTP_NOT_FOUND);
        }
        
        return new JsonResponse([
            'name' => ucwords(str_replace('-', ' ', $ability['name'])),
            'description' => $ability['description']
        ]);
    }

    #[Route('/api/discord/item/{name}', name: 'api_discord_item', methods: ['GET'])]
    public function itemInfo(string $name): JsonResponse
    {
        $nameClean = strtolower(trim($name));
        $nameKebab = str_replace(' ', '-', $nameClean);
        
        $item = $this->pokeApiService->getItemDetails($nameKebab);
        
        if ($item['description'] === 'Item recomendado para ativar a estratégia.') {
            return new JsonResponse(['error' => 'Item não encontrado.'], Response::HTTP_NOT_FOUND);
        }
        
        return new JsonResponse([
            'name' => ucwords(str_replace('-', ' ', $item['name'])),
            'description' => $item['description'],
            'sprite' => sprintf('https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/items/%s.png', $item['name'])
        ]);
    }

    #[Route('/api/discord/game/random', name: 'api_discord_game_random', methods: ['GET'])]
    public function gameRandom(): JsonResponse
    {
        try {
            $basicList = $this->pokeApiService->getPokemonBasicList();
            if (empty($basicList)) {
                return new JsonResponse(['error' => 'Nenhum Pokémon disponível.'], Response::HTTP_NOT_FOUND);
            }
            
            $randomPokemon = $basicList[array_rand($basicList)];
            $details = $this->pokeApiService->getPokemonDetails($randomPokemon['name']);
            
            $name = $details['name'];
            $speciesName = $details['species_name'] ?? $name;
            $description = $details['description'];
            
            $toCensor = array_unique([
                $name,
                $speciesName,
                str_replace('-', ' ', $name),
                str_replace('-', ' ', $speciesName),
            ]);
            
            foreach ($toCensor as $word) {
                if (strlen($word) > 2) {
                    $pattern = '/\b' . preg_quote($word, '/') . '(s|’s|\'s)?\b/i';
                    $description = preg_replace($pattern, '`????`', $description);
                }
            }
            
            $generation = \App\Service\PokeApi\PokeApiValidator::getGenerationById($details['id']);
            
            return new JsonResponse([
                'id' => $details['id'],
                'name' => $details['name'],
                'description' => $description,
                'types' => $details['types'],
                'stats' => $details['stats'],
                'generation' => $generation,
                'sprite' => $details['sprite_official']
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Erro ao buscar Pokémon aleatório: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/discord/type/{name}', name: 'api_discord_type', methods: ['GET'])]
    public function typeInfo(string $name): JsonResponse
    {
        $nameClean = strtolower(trim($name));
        
        $typeTranslations = [
            'normal' => 'normal', 'fogo' => 'fire', 'água' => 'water', 'agua' => 'water',
            'grama' => 'grass', 'planta' => 'grass', 'elétrico' => 'electric', 'eletrico' => 'electric',
            'gelo' => 'ice', 'lutador' => 'fighting', 'veneno' => 'poison', 'terra' => 'ground',
            'voador' => 'flying', 'psíquico' => 'psychic', 'psiquico' => 'psychic', 'inseto' => 'bug',
            'pedra' => 'rock', 'rocha' => 'rock', 'fantasma' => 'ghost', 'dragão' => 'dragon',
            'dragao' => 'dragon', 'sombrio' => 'dark', 'aço' => 'steel', 'aco' => 'steel',
            'fada' => 'fairy',
        ];
        
        $targetType = $typeTranslations[$nameClean] ?? $nameClean;
        
        $typeDetails = $this->pokeApiService->getTypeDetails($targetType);
        
        if (empty($typeDetails['damage_relations'])) {
            return new JsonResponse(['error' => 'Tipo não encontrado.'], Response::HTTP_NOT_FOUND);
        }
        
        return new JsonResponse([
            'name' => ucfirst($targetType),
            'name_pt' => array_search($targetType, $typeTranslations) ?: ucfirst($targetType),
            'damage_relations' => $typeDetails['damage_relations']
        ]);
    }

    #[Route('/api/discord/tier-list', name: 'api_discord_tier_list_list', methods: ['GET'])]
    public function listTierLists(Request $request): JsonResponse
    {
        $tagFilter = $request->query->get('tag', '');
        $searchFilter = $request->query->get('search', '');

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('t')
           ->from(\App\Entity\TierList::class, 't')
           ->orderBy('t.createdAt', 'DESC')
           ->setMaxResults(15);

        if (!empty($tagFilter)) {
            $qb->andWhere('t.tags LIKE :tag')
               ->setParameter('tag', '%"' . $tagFilter . '"%');
        }

        if (!empty($searchFilter)) {
            $qb->andWhere('t.title LIKE :search')
               ->setParameter('search', '%' . $searchFilter . '%');
        }

        $tierLists = $qb->getQuery()->getResult();

        $data = [];
        foreach ($tierLists as $t) {
            $data[] = [
                'id' => $t->getId(),
                'title' => $t->getTitle(),
                'user' => $t->getUser() ? $t->getUser()->getUsername() : 'Anônimo',
                'tags' => $t->getTags(),
                'created_at' => $t->getCreatedAt()->format('Y-m-d H:i:s'),
                'url' => $this->generateUrl('app_tier_list_view', ['id' => $t->getId()], UrlGeneratorInterface::ABSOLUTE_URL)
            ];
        }

        return new JsonResponse($data);
    }

    #[Route('/api/discord/tier-list/{idOrTitle}', name: 'api_discord_tier_list_show', methods: ['GET'])]
    public function showTierList(string $idOrTitle): JsonResponse
    {
        $repo = $this->entityManager->getRepository(\App\Entity\TierList::class);

        if (is_numeric($idOrTitle)) {
            $tierList = $repo->find((int)$idOrTitle);
        } else {
            // Busca por título parcial
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('t')
               ->from(\App\Entity\TierList::class, 't')
               ->where('t.title LIKE :title')
               ->setParameter('title', '%' . $idOrTitle . '%')
               ->orderBy('t.createdAt', 'DESC')
               ->setMaxResults(1);
            $tierLists = $qb->getQuery()->getResult();
            $tierList = !empty($tierLists) ? $tierLists[0] : null;
        }

        if (!$tierList) {
            return new JsonResponse(['error' => 'Tier List não encontrada.'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'id' => $tierList->getId(),
            'title' => $tierList->getTitle(),
            'user' => $tierList->getUser() ? $tierList->getUser()->getUsername() : 'Anônimo',
            'tags' => $tierList->getTags(),
            'created_at' => $tierList->getCreatedAt()->format('Y-m-d H:i:s'),
            'url' => $this->generateUrl('app_tier_list_view', ['id' => $tierList->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
            'state' => $tierList->getState()
        ]);
    }
}


