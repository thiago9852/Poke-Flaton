<?php

namespace App\Controller;

use App\Service\PokeApiService;
use App\Repository\MovesetRepository;
use App\Enum\TypeEnum;
use App\Entity\PokemonAccess;
use App\Repository\PokemonAccessRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

class PokemonController extends AbstractController
{
    private PokeApiService $pokeApiService;

    public function __construct(PokeApiService $pokeApiService)
    {
        $this->pokeApiService = $pokeApiService;
    }

    #[Route('/', name: 'app_home')]
    public function home(PokemonAccessRepository $pokemonAccessRepository): Response
    {
        $trending = $pokemonAccessRepository->findTrending(8);
        $trendingPokemons = [];

        if (!empty($trending)) {
            $names = array_map(fn($t) => $t->getPokemonName(), $trending);
            $details = $this->pokeApiService->getPokemonDetailsBatchByNames($names);

            foreach ($trending as $access) {
                $nameLower = strtolower($access->getPokemonName());
                if (isset($details[$nameLower])) {
                    $p = $details[$nameLower];
                    $trendingPokemons[] = [
                        'id' => $p['id'],
                        'name' => $p['name'],
                        'dex_id' => $p['species_id'],
                        'sprite' => $p['sprite_official'],
                        'types' => $p['types'],
                        'views' => $access->getViews(),
                    ];
                }
            }
        }

        // Metadados das gerações de pokémon
        $allGenerationsMetadata = [
            1 => ['number' => 1, 'games' => 'Red & Blue', 'region' => 'Kanto'],
            2 => ['number' => 2, 'games' => 'Gold & Silver', 'region' => 'Johto'],
            3 => ['number' => 3, 'games' => 'Ruby & Sapphire', 'region' => 'Hoenn'],
            4 => ['number' => 4, 'games' => 'Diamond & Pearl', 'region' => 'Sinnoh'],
            5 => ['number' => 5, 'games' => 'Black & White', 'region' => 'Unova'],
            6 => ['number' => 6, 'games' => 'X & Y', 'region' => 'Kalos'],
            7 => ['number' => 7, 'games' => 'Sun & Moon', 'region' => 'Alola'],
            8 => ['number' => 8, 'games' => 'Sword & Shield', 'region' => 'Galar'],
            9 => ['number' => 9, 'games' => 'Scarlet & Violet', 'region' => 'Paldea'],
        ];

        $allowedGens = $this->pokeApiService->getAllowedGenerations();
        $basicList = $this->pokeApiService->getPokemonBasicList();

        $genCounts = [];
        foreach ($basicList as $p) {
            $gen = PokeApiService::getGenerationById($p['id']);
            if ($gen > 0) {
                $genCounts[$gen] = ($genCounts[$gen] ?? 0) + 1;
            }
        }

        $generations = [];
        foreach ($allowedGens as $genNum) {
            if (isset($allGenerationsMetadata[$genNum])) {
                $meta = $allGenerationsMetadata[$genNum];
                $meta['count'] = $genCounts[$genNum] ?? 0;
                $generations[] = $meta;
            }
        }

        return $this->render('index/index.html.twig', [
            'trendingPokemons' => $trendingPokemons,
            'generations' => $generations,
        ]);
    }

    #[Route('/pokemons', name: 'app_pokemon_index')]
    public function index(Request $request): Response
    {
        $search = $request->query->get('q');
        $typeFilter = $request->query->get('type');
        $genFilter = $request->query->get('gen');
        $sort = $request->query->get('sort', '');

        $page = max(1, $request->query->getInt('page', 1));
        $limit = 24;
        $offset = ($page - 1) * $limit;

        // Pega a lista base inicial baseada no filtro de tipo
        if (!empty($typeFilter)) {
            $basicList = $this->pokeApiService->getPokemonBasicListByType($typeFilter);
        } else {
            $basicList = $this->pokeApiService->getPokemonBasicList();
        }

        // Filtra pela geração se especificado
        if (!empty($genFilter)) {
            $genInt = (int)$genFilter;
            $basicList = array_filter($basicList, function ($p) use ($genInt) {
                return PokeApiService::getGenerationById($p['id']) === $genInt;
            });
        }

        // Filtra pelo termo de busca
        if (!empty($search)) {
            $searchLower = strtolower(trim($search));
            $basicList = array_filter($basicList, function ($p) use ($searchLower) {
                return str_contains($p['name'], $searchLower) || strval($p['id']) === $searchLower;
            });
        }

        // Adiciona megas se não estiver filtrando por tipo
        if (empty($typeFilter)) {
            $finalList = [];
            $megas = $this->pokeApiService->getMegaEvolutions();
            foreach ($basicList as $p) {
                $finalList[] = $p;
                if (isset($megas[$p['id']])) {
                    foreach ($megas[$p['id']] as $mega) {
                        if ($this->pokeApiService->isPokemonAllowed($mega['id'])) {
                            // Se houver um termo de busca, verifica se o mega corresponde
                            if (!empty($search)) {
                                $searchLower = strtolower(trim($search));
                                if (!str_contains($mega['name'], $searchLower) && $searchLower !== 'mega') {
                                    continue;
                                }
                            }
                            $finalList[] = [
                                'id' => $mega['id'],
                                'name' => $mega['name'],
                                'sprite' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/' . $mega['id'] . '.png',
                                'types' => $mega['types'],
                                'dex_id' => $p['id']
                            ];
                        }
                    }
                }
            }

            // Se a busca contém 'mega', devemos garantir que também incluímos quaisquer megas permitidos correspondentes à consulta
            // mesmo que sua forma base não tenha correspondido à consulta
            if (!empty($search) && str_contains(strtolower($search), 'mega')) {
                $searchLower = strtolower(trim($search));
                foreach ($this->pokeApiService->getMegaEvolutions() as $baseId => $megasArr) {
                    if (!empty($genFilter) && PokeApiService::getGenerationById($baseId) !== (int)$genFilter) {
                        continue;
                    }
                    foreach ($megasArr as $mega) {
                        if (!$this->pokeApiService->isPokemonAllowed($mega['id'])) {
                            continue;
                        }
                        if (str_contains($mega['name'], $searchLower) || $searchLower === 'mega') {
                            // Verifica se este mega já está na lista
                            $exists = false;
                            foreach ($finalList as $existing) {
                                if ($existing['id'] === $mega['id']) {
                                    $exists = true;
                                    break;
                                }
                            }
                            if (!$exists) {
                                $finalList[] = [
                                    'id' => $mega['id'],
                                    'name' => $mega['name'],
                                    'sprite' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/' . $mega['id'] . '.png',
                                    'types' => $mega['types'],
                                    'dex_id' => $baseId
                                ];
                            }
                        }
                    }
                }
            }

            $basicList = $finalList;
        }

        // Ordena a lista base
        usort($basicList, function ($a, $b) use ($sort) {
            $dexIdA = $a['dex_id'] ?? $a['id'];
            $dexIdB = $b['dex_id'] ?? $b['id'];

            switch ($sort) {
                case 'number_desc':
                    if ($dexIdA === $dexIdB) {
                        return $b['id'] <=> $a['id'];
                    }
                    return $dexIdB <=> $dexIdA;

                case 'name_asc':
                    return strcasecmp($a['name'], $b['name']);

                case 'name_desc':
                    return strcasecmp($b['name'], $a['name']);

                case 'number_asc':
                default:
                    if ($dexIdA === $dexIdB) {
                        return $a['id'] <=> $b['id'];
                    }
                    return $dexIdA <=> $dexIdB;
            }
        });

        // Pagina a lista base
        $totalCount = count($basicList);
        $pagedBasic = array_slice($basicList, $offset, $limit);

        // Busca os detalhes dos pokémons na lista paginada
        $pokemons = $this->pokeApiService->getPokemonDetailsBatch($pagedBasic);

        $lastPage = (int) ceil($totalCount / $limit);

        return $this->render('pokemon/index.html.twig', [
            'pokemons' => $pokemons,
            'currentPage' => $page,
            'lastPage' => $lastPage,
            'allTypes' => TypeEnum::getCasesForModule('type'),
            'selectedType' => $typeFilter,
            'selectedGen' => $genFilter,
            'allowedGenerations' => $this->pokeApiService->getAllowedGenerations(),
            'search' => $search,
            'sort' => $sort,
        ]);
    }

    #[Route('/pokemon/{name}', name: 'app_pokemon_detail', methods: ['GET'])]
    public function detail(
        string $name,
        MovesetRepository $movesetRepository,
        PokemonAccessRepository $pokemonAccessRepository,
        EntityManagerInterface $entityManager
    ): Response {

        try {
            $pokemon = $this->pokeApiService->getPokemonDetails($name);
            $isAllowed = $this->pokeApiService->isPokemonAllowed($pokemon['id']);
            if (!$isAllowed && $pokemon['id'] >= 10000 && isset($pokemon['species_id'])) {
                $isAllowed = $this->pokeApiService->isPokemonAllowed($pokemon['species_id']);
            }
            if (!$isAllowed) {
                throw $this->createNotFoundException('Pokémon não encontrado.');
            }
        } catch (\Exception $e) {
            throw $this->createNotFoundException('Pokémon não encontrado.');
        }

        // Incrementa ou cria o registro de acesso para este Pokémon
        try {
            $pokemonAccess = $pokemonAccessRepository->findOneBy(['pokemonName' => $pokemon['name']]);
            if (!$pokemonAccess) {
                $pokemonAccess = new PokemonAccess();
                $pokemonAccess->setPokemonName($pokemon['name']);
                $pokemonAccess->setPokemonId($pokemon['id']);
                $pokemonAccess->setViews(0);
            }
            $pokemonAccess->incrementViews();
            $pokemonAccess->setLastAccessedAt(new \DateTime());
            $entityManager->persist($pokemonAccess);
            $entityManager->flush();
        } catch (\Exception $e) {
            // Ignore
        }

        // Buscar a linha evolutiva completa
        $evolutionChain = $this->pokeApiService->getPokemonEvolutionChain($pokemon['species_name']);

        // Buscar Pokémon anterior com base na espécie
        $prevPokemon = null;
        for ($prevId = $pokemon['species_id'] - 1; $prevId >= 1; $prevId--) {
            if (!$this->pokeApiService->isPokemonAllowed($prevId)) {
                continue;
            }
            try {
                $prevPokemon = $this->pokeApiService->getPokemonDetails(strval($prevId));
                break;
            } catch (\Exception $e) {
                // ignore
            }
        }

        // Buscar Pokémon próximo com base na espécie
        $nextPokemon = null;
        for ($nextId = $pokemon['species_id'] + 1; $nextId <= 1025; $nextId++) {
            if (!$this->pokeApiService->isPokemonAllowed($nextId)) {
                continue;
            }
            try {
                $nextPokemon = $this->pokeApiService->getPokemonDetails(strval($nextId));
                break;
            } catch (\Exception $e) {
                // ignore
            }
        }

        // Buscar movesets cadastrados no banco
        $movesets = $movesetRepository->findBy(['pokemonName' => $pokemon['name']], ['votes' => 'DESC']);

        // Buscar detalhes de cada golpe, habilidade e nature contidos nos movesets
        $moveDetails = [];
        $abilityDetails = [];
        
        $allNatures = $this->pokeApiService->getNatures();
        $naturesMap = [];
        foreach ($allNatures as $n) {
            $naturesMap[$n['name']] = $n;
        }
        $itemDetails = [];

        foreach ($movesets as $moveset) {
            foreach ($moveset->getMoves() as $moveName) {
                if (!empty($moveName) && !isset($moveDetails[$moveName])) {
                    $moveDetails[$moveName] = $this->pokeApiService->getMoveDetails($moveName);
                }
            }
            $abilityName = $moveset->getAbility();
            if (!empty($abilityName) && !isset($abilityDetails[$abilityName])) {
                $abilityDetails[$abilityName] = $this->pokeApiService->getAbilityDetails($abilityName);
            }
            $itemName = $moveset->getHeldItem();
            if (!empty($itemName) && !isset($itemDetails[$itemName])) {
                $itemDetails[$itemName] = $this->pokeApiService->getItemDetails($itemName);
            }
        }

        $typeDetails = [];
        foreach ($pokemon['types'] as $type) {
            $typeDetails[$type] = $this->pokeApiService->getTypeDetails($type);
        }

        // Calcular a nature mais recomendada por tipo de moveset (padrão, pvp, dg)
        // Baseado na quantidade de movesets criados com cada nature
        $recommendedNatures = ['padrao' => null, 'pvp' => null, 'dg' => null];
        foreach (['padrao', 'pvp', 'dg'] as $tabType) {
            $natureCounts = [];
            foreach ($movesets as $m) {
                if ($m->getType() === $tabType) {
                    $n = $m->getNature();
                    if (!empty($n)) {
                        $natureCounts[$n] = ($natureCounts[$n] ?? 0) + 1;
                    }
                }
            }
            if (!empty($natureCounts)) {
                arsort($natureCounts); // Ordena mantendo a chave (nature), do maior para o menor
                $recommendedNatures[$tabType] = array_key_first($natureCounts);
            }
        }

        $locations = $entityManager->getRepository(\App\Entity\PokemonLocation::class)->findBy(
            ['pokemonName' => $pokemon['name']],
            ['createdAt' => 'ASC']
        );

        return $this->render('pokemon/detail.html.twig', [
            'pokemon' => $pokemon,
            'evolutionChain' => $evolutionChain,
            'prevPokemon' => $prevPokemon,
            'nextPokemon' => $nextPokemon,
            'movesets' => $movesets,
            'moveDetails' => $moveDetails,
            'abilityDetails' => $abilityDetails,
            'itemDetails' => $itemDetails,
            'naturesMap' => $naturesMap,
            'typeDetails' => $typeDetails,
            'recommendedNatures' => $recommendedNatures,
            'locations' => $locations,
        ]);
    }

    #[Route('/api/pokemon/search', name: 'api_pokemon_search', methods: ['GET'])]
    public function searchAjax(Request $request): JsonResponse
    {
        $query = strtolower(trim($request->query->get('q', '')));
        if (strlen($query) < 2) {
            return new JsonResponse([]);
        }

        $allBasicList = $this->pokeApiService->getPokemonBasicList();
        
        // Filtra instantaneamente a lista que já está em cache
        $filtered = array_filter($allBasicList, function ($p) use ($query) {
            return str_contains($p['name'], $query) || strval($p['id']) === $query;
        });

        // Adicionar megas se buscar por "mega"
        if (str_contains($query, 'mega')) {
            foreach ($this->pokeApiService->getMegaEvolutions() as $baseId => $megas) {
                foreach ($megas as $mega) {
                    if (!$this->pokeApiService->isPokemonAllowed($mega['id'])) {
                        continue;
                    }
                    if (str_contains($mega['name'], $query) || $query === 'mega') {
                        $filtered[] = [
                            'id' => $mega['id'],
                            'name' => $mega['name'],
                            'sprite' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/' . $mega['id'] . '.png',
                        ];
                    }
                }
            }
        }

        // Limitar a 8 resultados para não estourar a tela do usuário
        $filtered = array_slice($filtered, 0, 8); 
        
        $results = [];
        foreach ($filtered as $item) {
            $results[] = [
                'id' => $item['id'],
                'name' => ucfirst(str_replace('-', ' ', $item['name'])),
                'sprite' => $item['sprite'],
                'url' => $this->generateUrl('app_pokemon_detail', ['name' => $item['name']])
            ];
        }

        return new JsonResponse($results);
    }

    #[Route('/pokedex', name: 'app_pokedex')]
    public function pokedex(Request $request): Response
    {
        $user = $this->getUser();
        $caught = [];
        if ($user) {
            $caught = $user->getCaughtPokemon();
        }

        // Pega a lista básica de todos os Pokémons permitidos
        $basicList = $this->pokeApiService->getPokemonBasicList();

        $allGenerationsMetadata = [
            1 => ['number' => 1, 'games' => 'Red & Blue', 'region' => 'Kanto'],
            2 => ['number' => 2, 'games' => 'Gold & Silver', 'region' => 'Johto'],
            3 => ['number' => 3, 'games' => 'Ruby & Sapphire', 'region' => 'Hoenn'],
            4 => ['number' => 4, 'games' => 'Diamond & Pearl', 'region' => 'Sinnoh'],
            5 => ['number' => 5, 'games' => 'Black & White', 'region' => 'Unova'],
            6 => ['number' => 6, 'games' => 'X & Y', 'region' => 'Kalos'],
            7 => ['number' => 7, 'games' => 'Sun & Moon', 'region' => 'Alola'],
            8 => ['number' => 8, 'games' => 'Sword & Shield', 'region' => 'Galar'],
            9 => ['number' => 9, 'games' => 'Scarlet & Violet', 'region' => 'Paldea'],
        ];

        $allowedGens = $this->pokeApiService->getAllowedGenerations();

        $pokedexList = [];
        $totalRegistered = 0;
        
        foreach ($basicList as $p) {
            $gen = PokeApiService::getGenerationById($p['id']);
            if (!in_array($gen, $allowedGens)) {
                continue;
            }

            $pNameLower = strtolower($p['name']);
            $isCaught = false;
            $caughtAt = null;

            // Verifica se o usuário capturou o Pokémon
            if (array_key_exists($pNameLower, $caught)) {
                $isCaught = true;
                $caughtAt = $caught[$pNameLower];
            } elseif (in_array($pNameLower, $caught)) {
                $isCaught = true;
            }

            if ($isCaught) {
                $totalRegistered++;
            }

            $pokedexList[] = [
                'id' => $p['id'],
                'name' => $p['name'],
                'display_name' => ucfirst(str_replace('-', ' ', $p['name'])),
                'sprite' => $p['sprite'],
                'generation' => $gen,
                'isCaught' => $isCaught,
                'caughtAt' => $caughtAt
            ];
        }

        // Aplica o filtro de busca se presente
        $search = $request->query->get('q');
        if (!empty($search)) {
            $searchLower = strtolower(trim($search));
            $pokedexList = array_filter($pokedexList, function ($p) use ($searchLower) {
                return str_contains(strtolower($p['name']), $searchLower) || strval($p['id']) === $searchLower;
            });
        }

        // Aplica o filtro de geração se presente
        $genFilter = $request->query->get('gen');
        if (!empty($genFilter)) {
            $genInt = (int)$genFilter;
            $pokedexList = array_filter($pokedexList, function ($p) use ($genInt) {
                return $p['generation'] === $genInt;
            });
        }

        // Ordena por id crescente
        usort($pokedexList, fn($a, $b) => $a['id'] <=> $b['id']);

        // Coleta nomes das regiões e contagens de gerações para o cabeçalho ou filtros
        $generationsInfo = [];
        foreach ($allowedGens as $genNum) {
            if (isset($allGenerationsMetadata[$genNum])) {
                $generationsInfo[] = $allGenerationsMetadata[$genNum];
            }
        }

        return $this->render('pokemon/pokedex.html.twig', [
            'pokedexList' => $pokedexList,
            'totalCount' => count($pokedexList),
            'totalRegistered' => $totalRegistered,
            'allGenerations' => $generationsInfo,
            'selectedGen' => $genFilter,
            'search' => $search
        ]);
    }
}
