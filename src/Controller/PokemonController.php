<?php

namespace App\Controller;

use App\Service\PokeApiService;
use App\Repository\MovesetRepository;
use App\Enum\TypeEnum;
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
    public function home(): Response
    {
        return $this->render('index/index.html.twig', []);
    }

    #[Route('/pokemons', name: 'app_pokemon_index')]
    public function index(Request $request): Response
    {
        $search = $request->query->get('q');
        $typeFilter = $request->query->get('type');
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

        // 2. Filtra pelo termo de busca
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
            'search' => $search,
            'sort' => $sort,
        ]);
    }

    #[Route('/pokemon/{name}', name: 'app_pokemon_detail')]
    public function detail(
        string $name,
        MovesetRepository $movesetRepository
    ): Response {

        try {
            $pokemon = $this->pokeApiService->getPokemonDetails($name);
            if (!$this->pokeApiService->isPokemonAllowed($pokemon['id'])) {
                throw $this->createNotFoundException('Pokémon não encontrado.');
            }
        } catch (\Exception $e) {
            throw $this->createNotFoundException('Pokémon não encontrado.');
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
}
