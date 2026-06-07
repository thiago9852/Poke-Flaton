<?php

namespace App\Controller;

use App\Service\PokeApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Enum\TypeEnum;

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

        $page = max(1, $request->query->getInt('page', 1));
        $limit = 24;
        $offset = ($page - 1) * $limit;

        if (!empty($typeFilter)) {
            // Filtragem por tipo: o serviço faz o slice e busca detalhes de apenas 24
            $result = $this->pokeApiService->getPokemonByType($typeFilter, $limit, $offset);
            $pokemons = $result['results'];
            $totalCount = $result['count'];
        } elseif (!empty($search)) {
            // Busca por termo: pega lista básica leve, filtra, faz slice e busca detalhes do lote de 24
            $search = strtolower(trim($search));
            $allBasicList = $this->pokeApiService->getPokemonBasicList();

            $filteredBasic = array_filter($allBasicList, function ($p) use ($search) {
                return str_contains($p['name'], $search) || strval($p['id']) === $search;
            });

            // Inserir as megas correspondentes no basic list filtrado
            $finalBasic = [];
            foreach ($filteredBasic as $p) {
                $finalBasic[] = $p;
                $megas = $this->pokeApiService->getMegaEvolutions();
                if (isset($megas[$p['id']])) {
                    foreach ($megas[$p['id']] as $mega) {
                        if ($this->pokeApiService->isPokemonAllowed($mega['id'])) {
                            $finalBasic[] = [
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

            // Se o termo pesquisado contiver "mega", adiciona as megas correspondentes (caso não tenham sido adicionadas pelo filtro base)
            if (str_contains($search, 'mega')) {
                $finalBasic = [];
                foreach ($this->pokeApiService->getMegaEvolutions() as $baseId => $megas) {
                    foreach ($megas as $mega) {
                        if (!$this->pokeApiService->isPokemonAllowed($mega['id'])) {
                            continue;
                        }
                        if (str_contains($mega['name'], $search) || $search === 'mega') {
                            $finalBasic[] = [
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

            $totalCount = count($finalBasic);
            $pagedBasic = array_slice($finalBasic, $offset, $limit);

            $pokemons = [];
            $basicToFetch = [];
            foreach ($pagedBasic as $item) {
                if ($item['id'] > 10000) {
                    $pokemons[] = $item;
                } else {
                    $basicToFetch[] = $item;
                }
            }

            if (!empty($basicToFetch)) {
                $fetchedPokemons = $this->pokeApiService->getPokemonDetailsBatch($basicToFetch);
                $pokemons = array_merge($pokemons, $fetchedPokemons);
                usort($pokemons, function ($a, $b) use ($pagedBasic) {
                    $posA = array_search($a['name'], array_column($pagedBasic, 'name'));
                    $posB = array_search($b['name'], array_column($pagedBasic, 'name'));
                    return $posA <=> $posB;
                });
            }
        } else {
            $allBasicList = $this->pokeApiService->getPokemonBasicList();
            $totalCount = count($allBasicList);
            $pagedBasic = array_slice($allBasicList, $offset, $limit);

            $pokemons = $this->pokeApiService->getPokemonDetailsBatch($pagedBasic);

            // Inserir as megas correspondentes no list
            $finalPokemons = [];
            foreach ($pokemons as $p) {
                $finalPokemons[] = $p;
                $megas = $this->pokeApiService->getMegaEvolutions();
                if (isset($megas[$p['id']])) {
                    foreach ($megas[$p['id']] as $mega) {
                        if ($this->pokeApiService->isPokemonAllowed($mega['id'])) {
                            $finalPokemons[] = [
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
            $pokemons = $finalPokemons;
        }

        $lastPage = (int) ceil($totalCount / $limit);


        return $this->render('pokemon/index.html.twig', [
            'pokemons' => $pokemons,
            'currentPage' => $page,
            'lastPage' => $lastPage,
            'allTypes' => TypeEnum::getCasesForModule('type'),
            'selectedType' => $typeFilter,
            'search' => $search,
        ]);
    }

    #[Route('/pokemon/{name}', name: 'app_pokemon_detail')]
    public function detail(string $name, \App\Repository\MovesetRepository $movesetRepository): Response
    {
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
        ]);
    }
}
