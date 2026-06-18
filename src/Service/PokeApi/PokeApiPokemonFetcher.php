<?php

namespace App\Service\PokeApi;

use App\Repository\EvolutionRuleRepository;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class PokeApiPokemonFetcher
{
    private HttpClientInterface $httpClient;
    private CacheInterface $cache;
    private EvolutionRuleRepository $evolutionRuleRepository;
    private PokeApiValidator $validator;

    public function __construct(
        HttpClientInterface $httpClient,
        CacheInterface $cache,
        EvolutionRuleRepository $evolutionRuleRepository,
        PokeApiValidator $validator
    ) {
        $this->httpClient = $httpClient;
        $this->cache = $cache;
        $this->evolutionRuleRepository = $evolutionRuleRepository;
        $this->validator = $validator;
    }

    /**
     * Obter lista de Pokémons paginada e otimizada (24 por página padrão)
     */
    public function getPokemonList(int $limit = 24, int $offset = 0): array
    {
        return $this->cache->get('pokemon_list_' . $limit . '_' . $offset, function (ItemInterface $item) use ($limit, $offset) {
            $item->expiresAfter(86400 * 7); // Cache por 7 dias

            $response = $this->httpClient->request('GET', sprintf('https://pokeapi.co/api/v2/pokemon?limit=%d&offset=%d', $limit, $offset));
            $data = $response->toArray();

            // Requisições paralelas para buscar os tipos de cada Pokémon da página atual (apenas $limit itens!)
            $responses = [];
            foreach ($data['results'] as $pokemon) {
                $responses[$pokemon['name']] = $this->httpClient->request('GET', $pokemon['url']);
            }

            $list = [];
            foreach ($data['results'] as $pokemon) {
                $name = $pokemon['name'];
                $id = null;
                $types = [];
                try {
                    $details = $responses[$name]->toArray();
                    $id = $details['id'];
                    foreach ($details['types'] as $t) {
                        $types[] = $t['type']['name'];
                    }
                } catch (\Exception $e) {
                    $parts = explode('/', rtrim($pokemon['url'], '/'));
                    $id = (int) end($parts);
                }

                $list[] = [
                    'id' => $id,
                    'name' => $name,
                    'sprite' => sprintf('https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/%d.png', $id),
                    'types' => $types
                ];
            }

            return [
                'results' => $list,
                'count' => $data['count']
            ];
        });
    }

    /**
     * Obter lista de Pokémons filtrados por tipo com paginação em cache
     */
    public function getPokemonByType(string $type, int $limit = 24, int $offset = 0): array
    {
        return $this->cache->get('pokemon_type_' . $type . '_' . $limit . '_' . $offset, function (ItemInterface $item) use ($type, $limit, $offset) {
            $item->expiresAfter(86400 * 7); // Cache por 7 dias

            // 1. Busca todos os pokemons daquele tipo
            $response = $this->httpClient->request('GET', 'https://pokeapi.co/api/v2/type/' . strtolower($type));
            $data = $response->toArray();

            $allPokemonOfType = $data['pokemon'];
            $filteredPokemon = [];
            foreach ($allPokemonOfType as $p) {
                $parts = explode('/', rtrim($p['pokemon']['url'], '/'));
                $id = (int) end($parts);

                if (!$this->validator->isPokemonAllowed($id)) {
                    continue;
                }

                $baseId = $this->validator->getBaseSpeciesId($id);
                $filteredPokemon[] = [
                    'pokemon' => $p['pokemon'],
                    'id' => $id,
                    'base_id' => $baseId
                ];
            }

            // Ordenar por base_id para que as Megas fiquem juntas de suas formas base
            usort($filteredPokemon, function ($a, $b) {
                if ($a['base_id'] === $b['base_id']) {
                    return $a['id'] <=> $b['id'];
                }
                return $a['base_id'] <=> $b['base_id'];
            });

            $totalCount = count($filteredPokemon);

            // 2. Aplica o slice para a página atual
            $pagedPokemon = array_slice($filteredPokemon, $offset, $limit);

            // 3. Faz requisições paralelas para os detalhes de apenas esses $limit pokemons
            $responses = [];
            foreach ($pagedPokemon as $p) {
                $pokemonName = $p['pokemon']['name'];
                $responses[$pokemonName] = $this->httpClient->request('GET', $p['pokemon']['url']);
            }

            $list = [];
            foreach ($pagedPokemon as $p) {
                $pokemonName = $p['pokemon']['name'];
                $id = $p['id'];
                $types = [];
                try {
                    $details = $responses[$pokemonName]->toArray();
                    foreach ($details['types'] as $t) {
                        $types[] = $t['type']['name'];
                    }
                } catch (\Exception $e) {
                    // fallback
                }

                $list[] = [
                    'id' => $id,
                    'name' => $pokemonName,
                    'sprite' => sprintf('https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/%d.png', $id),
                    'types' => $types,
                    'dex_id' => $p['base_id']
                ];
            }

            return [
                'results' => $list,
                'count' => $totalCount
            ];
        });
    }

    /**
     * Obter detalhes completos de um Pokémon
     */
    public function getPokemonDetails(string $nameOrId): array
    {
        $cacheKey = 'pokemon_details_' . strtolower($nameOrId);
        $details = $this->cache->get($cacheKey, function (ItemInterface $item) use ($nameOrId) {
            $item->expiresAfter(86400 * 7); // Cache por 7 dias
            return $this->fetchPokemonDetailsRaw($nameOrId);
        });

        // Se o cache for antigo e não contiver os novos campos, força a re-busca
        if (!isset($details['weight'])) {
            $this->cache->delete($cacheKey);
            $details = $this->cache->get($cacheKey, function (ItemInterface $item) use ($nameOrId) {
                $item->expiresAfter(86400 * 7);
                return $this->fetchPokemonDetailsRaw($nameOrId);
            });
        }

        return $details;
    }

    /**
     * Busca os detalhes reais do Pokémon na API
     */
    private function fetchPokemonDetailsRaw(string $nameOrId): array
    {
        $response = $this->httpClient->request('GET', 'https://pokeapi.co/api/v2/pokemon/' . strtolower($nameOrId));
        $data = $response->toArray();

        // Status base
        $stats = [];
        foreach ($data['stats'] as $s) {
            $stats[$s['stat']['name']] = $s['base_stat'];
        }

        // Tipos
        $types = [];
        foreach ($data['types'] as $t) {
            $types[] = $t['type']['name'];
        }

        // Habilidades possíveis
        $abilities = [];
        foreach ($data['abilities'] as $a) {
            $abilities[] = $a['ability']['name'];
        }

        // Itens dropados selvagens (held items)
        $heldItems = [];
        foreach ($data['held_items'] as $hi) {
            $itemName = $hi['item']['name'];
            $maxRarity = 0;
            foreach ($hi['version_details'] as $vd) {
                if ($vd['rarity'] > $maxRarity) {
                    $maxRarity = $vd['rarity'];
                }
            }
            $heldItems[] = [
                'name' => $itemName,
                'chance' => $maxRarity,
                'sprite' => sprintf('https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/items/%s.png', $itemName)
            ];
        }

        // Movimentos que pode aprender
        $moves = [];
        foreach ($data['moves'] as $m) {
            $hasBase = false;
            $hasTM   = false;
            foreach ($m['version_group_details'] as $vgd) {
                $method = $vgd['move_learn_method']['name'];
                if ($method === 'machine') {
                    $hasTM = true;
                } elseif ($method === 'level-up') {
                    $hasBase = true;
                }
            }

            if ($hasBase && $hasTM) {
                $learnMethod = 'both';
            } elseif ($hasTM) {
                $learnMethod = 'TM';
            } else {
                $learnMethod = 'base';
            }

            $moves[$m['move']['name']] = [
                'name'         => $m['move']['name'],
                'learn_method' => $learnMethod
            ];
        }
        ksort($moves);

        $speciesUrl = $data['species']['url'];
        $speciesParts = explode('/', rtrim($speciesUrl, '/'));
        $speciesId = (int) end($speciesParts);
        $speciesName = $data['species']['name'];

        // Fetch variedade de especies
        $speciesData = $this->cache->get('pokemon_species_' . $speciesId, function (ItemInterface $item) use ($speciesUrl) {
            $item->expiresAfter(86400 * 7);
            $resp = $this->httpClient->request('GET', $speciesUrl);
            return $resp->toArray();
        });

        $varieties = [];
        foreach ($speciesData['varieties'] as $v) {
            $vName = $v['pokemon']['name'];
            if (str_contains($vName, '-mega') || str_contains($vName, '-z')) {
                $varieties[] = [
                    'name' => $vName,
                    'display_name' => str_replace(['-x', '-y', '-mega', '-z'], [' X', ' Y', ' Mega', ' Z'], $vName)
                ];
            }
        }

        // Extrai a descrição/flavor text em português ou inglês
        $description = '';
        if (!empty($speciesData['flavor_text_entries'])) {
            foreach ($speciesData['flavor_text_entries'] as $entry) {
                if ($entry['language']['name'] === 'pt' || $entry['language']['name'] === 'pt-BR') {
                    $description = str_replace(["\n", "\f", "\r"], ' ', $entry['flavor_text']);
                    break;
                }
            }
            if (empty($description)) {
                foreach ($speciesData['flavor_text_entries'] as $entry) {
                    if ($entry['language']['name'] === 'en') {
                        $description = str_replace(["\n", "\f", "\r"], ' ', $entry['flavor_text']);
                        break;
                    }
                }
            }
        }

        return [
            'id' => $data['id'],
            'name' => $data['name'],
            'species_id' => $speciesId,
            'species_name' => $speciesName,
            'varieties' => $varieties,
            'sprite_official' => sprintf('https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/%d.png', $data['id']),
            'types' => $types,
            'stats' => $stats,
            'drops' => $heldItems,
            'moves' => $moves,
            'abilities' => $abilities,
            'weight' => $data['weight'] ?? 0,
            'height' => $data['height'] ?? 0,
            'description' => $description ?: 'Nenhuma descrição de Pokédex disponível para este Pokémon.'
        ];
    }

    /**
     * Obter lista leve contendo apenas nomes e IDs para busca/filtro rápido
     */
    public function getPokemonBasicList(): array
    {
        return $this->cache->get('pokemon_basic_list_configured', function (ItemInterface $item) {
            $item->expiresAfter(86400 * 30); // 30 dias
            $response = $this->httpClient->request('GET', 'https://pokeapi.co/api/v2/pokemon?limit=1200');
            $data = $response->toArray();

            $list = [];
            foreach ($data['results'] as $pokemon) {
                $parts = explode('/', rtrim($pokemon['url'], '/'));
                $id = (int) end($parts);

                // Ignorar variedades (IDs >= 10000) no basic list base
                if ($id >= 10000) {
                    continue;
                }

                if (!$this->validator->isPokemonAllowed($id)) {
                    continue;
                }
                $list[] = [
                    'id' => $id,
                    'name' => $pokemon['name'],
                    'sprite' => sprintf('https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/%d.png', $id),
                    'types' => [] // vazio por padrão para velocidade
                ];
            }
            return $list;
        });
    }

    /**
     * Obter lista de todos os Pokémons das gerações 1 a 6 para o mini-game,
     * incluindo lendários e míticos.
     */
    public function getPokemonGameList(): array
    {
        return $this->cache->get('pokemon_game_list_gens1_6', function (ItemInterface $item) {
            $item->expiresAfter(86400 * 30); // 30 dias
            $response = $this->httpClient->request('GET', 'https://pokeapi.co/api/v2/pokemon?limit=721');
            $data = $response->toArray();

            $list = [];
            foreach ($data['results'] as $pokemon) {
                $parts = explode('/', rtrim($pokemon['url'], '/'));
                $id = (int) end($parts);

                // Garante que está no range de Gen 1 a 6 (1 a 721)
                if ($id < 1 || $id > 721) {
                    continue;
                }

                $list[] = [
                    'id' => $id,
                    'name' => $pokemon['name'],
                    'sprite' => sprintf('https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/%d.png', $id),
                    'types' => [] // vazio por padrão para velocidade
                ];
            }
            return $list;
        });
    }

    /**
     * Obter lista básica de Pokémons filtrados por tipo para busca/ordenação rápida
     */
    public function getPokemonBasicListByType(string $type): array
    {
        return $this->cache->get('pokemon_basic_list_type_' . strtolower($type), function (ItemInterface $item) use ($type) {
            $item->expiresAfter(86400 * 7); // Cache por 7 dias

            $response = $this->httpClient->request('GET', 'https://pokeapi.co/api/v2/type/' . strtolower($type));
            $data = $response->toArray();

            $allPokemonOfType = $data['pokemon'];
            $list = [];
            foreach ($allPokemonOfType as $p) {
                $parts = explode('/', rtrim($p['pokemon']['url'], '/'));
                $id = (int) end($parts);

                if (!$this->validator->isPokemonAllowed($id)) {
                    continue;
                }

                $baseId = $this->validator->getBaseSpeciesId($id);
                $list[] = [
                    'id' => $id,
                    'name' => $p['pokemon']['name'],
                    'sprite' => sprintf('https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/%d.png', $id),
                    'dex_id' => $baseId,
                    'types' => [] // vazio por padrão para velocidade
                ];
            }

            return $list;
        });
    }

    /**
     * Buscar os detalhes (principalmente tipos) para um lote específico de pokémons concorrentemente
     */
    public function getPokemonDetailsBatch(array $pokemonBasicList): array
    {
        if (empty($pokemonBasicList)) {
            return [];
        }

        $responses = [];
        foreach ($pokemonBasicList as $p) {
            $responses[$p['name']] = $this->httpClient->request('GET', 'https://pokeapi.co/api/v2/pokemon/' . $p['name']);
        }

        $list = [];
        foreach ($pokemonBasicList as $p) {
            $name = $p['name'];
            $id = $p['id'];
            $types = [];
            try {
                $details = $responses[$name]->toArray();
                foreach ($details['types'] as $t) {
                    $types[] = $t['type']['name'];
                }
            } catch (\Exception $e) {
                // fallback
            }

            $list[] = [
                'id' => $id,
                'name' => $name,
                'sprite' => $p['sprite'],
                'types' => $types,
                'dex_id' => $p['dex_id'] ?? $id
            ];
        }
        return $list;
    }

    /**
     * Buscar os detalhes completos de um lote de nomes de Pokémons de forma concorrente e com cache
     */
    public function getPokemonDetailsBatchByNames(array $names): array
    {
        $detailsList = [];
        $missedNames = [];

         foreach ($names as $name) {
            $nameLower = strtolower($name);
            
            // 1. Verificar se o cache do lote (light) existe
            $batchCacheKey = 'pokemon_details_batch_' . $nameLower;
            $batchItem = $this->cache->getItem($batchCacheKey);
            if ($batchItem->isHit()) {
                $detailsList[$nameLower] = $batchItem->get();
                continue;
            }

            // 2. Alternativa: Se o cache detalhado completo existir, podemos usá-lo!
            $fullCacheKey = 'pokemon_details_' . $nameLower;
            $fullItem = $this->cache->getItem($fullCacheKey);
            if ($fullItem->isHit()) {
                $detailsList[$nameLower] = $fullItem->get();
                continue;
            }

            // 3. Caso contrário, precisamos buscar na API
            $missedNames[] = $nameLower;
        }

        if (!empty($missedNames)) {
            $responses = [];
            foreach ($missedNames as $name) {
                $responses[$name] = $this->httpClient->request('GET', 'https://pokeapi.co/api/v2/pokemon/' . $name);
            }

            foreach ($responses as $name => $response) {
                try {
                    $data = $response->toArray();
                    $stats = [];
                    foreach ($data['stats'] as $s) {
                        $stats[$s['stat']['name']] = $s['base_stat'];
                    }
                    $types = [];
                    foreach ($data['types'] as $t) {
                        $types[] = $t['type']['name'];
                    }
                    $abilities = [];
                    foreach ($data['abilities'] as $a) {
                        $abilities[] = $a['ability']['name'];
                    }
                    $heldItems = [];
                    foreach ($data['held_items'] as $hi) {
                        $itemName = $hi['item']['name'];
                        $maxRarity = 0;
                        foreach ($hi['version_details'] as $vd) {
                            if ($vd['rarity'] > $maxRarity) {
                                $maxRarity = $vd['rarity'];
                            }
                        }
                        $heldItems[] = [
                            'name' => $itemName,
                            'chance' => $maxRarity,
                            'sprite' => sprintf('https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/items/%s.png', $itemName)
                        ];
                    }

                    $speciesUrl = $data['species']['url'];
                    $speciesParts = explode('/', rtrim($speciesUrl, '/'));
                    $speciesId = (int) end($speciesParts);
                    $speciesName = $data['species']['name'];

                    $parsed = [
                        'id' => $data['id'],
                        'name' => $data['name'],
                        'species_id' => $speciesId,
                        'species_name' => $speciesName,
                        'varieties' => [],
                        'sprite_official' => sprintf('https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/%d.png', $data['id']),
                        'types' => $types,
                        'stats' => $stats,
                        'drops' => $heldItems,
                        'moves' => [],
                        'abilities' => $abilities
                    ];

                    // Salvar no cache específico de LOTE (batch), nunca sobrescrevendo o completo
                    $batchCacheKey = 'pokemon_details_batch_' . $name;
                    $batchItem = $this->cache->getItem($batchCacheKey);
                    $batchItem->set($parsed);
                    $batchItem->expiresAfter(86400 * 7);
                    $this->cache->save($batchItem);

                    $detailsList[$name] = $parsed;
                } catch (\Exception $e) {
                    // ignore
                }
            }
        }

        return $detailsList;
    }

    /**
     * Obter a linha evolutiva completa do Pokémon
     */
    public function getPokemonEvolutionChain(string $pokemonName): array
    {
        return $this->cache->get('evolution_chain_v2_for_' . $pokemonName, function (ItemInterface $item) use ($pokemonName) {
            $item->expiresAfter(86400 * 30); // 30 dias
            try {
                // 1. Busca espécie para obter a URL da cadeia evolutiva
                $response = $this->httpClient->request('GET', 'https://pokeapi.co/api/v2/pokemon-species/' . strtolower($pokemonName));
                $speciesData = $response->toArray();

                $chainUrl = $speciesData['evolution_chain']['url'];

                // 2. Busca os dados da cadeia evolutiva
                $chainResponse = $this->httpClient->request('GET', $chainUrl);
                $chainData = $chainResponse->toArray();

                return $this->parseEvolutionChainStages($chainData['chain']);
            } catch (\Exception $e) {
                return [];
            }
        });
    }

    /**
     * Mapeia a árvore de evolução agrupando os Pokémon por estágio evolutivo (bifurcações)
     */
    private function parseEvolutionChainStages(array $chainNode, int $stage = 1): array
    {
        $stages = [];

        $speciesName = $chainNode['species']['name'];
        $parts = explode('/', rtrim($chainNode['species']['url'], '/'));
        $speciesId = (int) end($parts);

        // Busca os tipos daquele pokemon
        $types = [];
        try {
            $response = $this->httpClient->request('GET', 'https://pokeapi.co/api/v2/pokemon/' . strtolower($speciesName));
            $data = $response->toArray();
            foreach ($data['types'] as $t) {
                $types[] = $t['type']['name'];
            }
        } catch (\Exception $e) {
            $types = ['normal']; // fallback
        }

        $stages[$stage][] = [
            'name'   => $speciesName,
            'id'     => $speciesId,
            'sprite' => sprintf('https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/%d.png', $speciesId),
            'types'  => $types
        ];

        if (!empty($chainNode['evolves_to'])) {
            foreach ($chainNode['evolves_to'] as $nextBranch) {
                $nextSpeciesName = $nextBranch['species']['name'];

                // Busca a regra de evolução customizada
                $rule = $this->evolutionRuleRepository->findOneBy([
                    'basePokemon'    => strtolower($speciesName),
                    'evolvedPokemon' => strtolower($nextSpeciesName)
                ]);

                $method = $rule ? $rule->getMethod() : 'Nível/Stone';

                // Processa recursivamente o próximo estágio
                $nextStages = $this->parseEvolutionChainStages($nextBranch, $stage + 1);

                // Associa o método de evolução correspondente a cada nó filho
                if (isset($nextStages[$stage + 1])) {
                    foreach ($nextStages[$stage + 1] as &$childNode) {
                        if ($childNode['name'] === $nextSpeciesName) {
                            $childNode['evolution_method'] = $method;
                        }
                    }
                }

                // Mescla os estágios filhos no array principal de estágios
                foreach ($nextStages as $sIndex => $sNodes) {
                    if (!isset($stages[$sIndex])) {
                        $stages[$sIndex] = [];
                    }
                    $stages[$sIndex] = array_merge($stages[$sIndex], $sNodes);
                }
            }
        }

        return $stages;
    }

    /**
     * Calcular o limite máximo de moves com base no estágio evolutivo e BST
     */
    public function calculateMaxMoves(string $pokemonName, array $stats): int
    {
        return $this->cache->get('max_moves_' . strtolower($pokemonName), function (ItemInterface $item) use ($pokemonName, $stats) {
            $item->expiresAfter(86400 * 30);

            $bst = array_sum($stats);

            try {
                $searchName = strtolower($pokemonName);
                if (str_contains($searchName, '-mega')) {
                    $searchName = explode('-mega', $searchName)[0];
                }

                // Busca a espécie para a cadeia evolutiva
                $speciesResp = $this->httpClient->request('GET', 'https://pokeapi.co/api/v2/pokemon-species/' . $searchName);
                $speciesData = $speciesResp->toArray();

                $chainUrl = $speciesData['evolution_chain']['url'];
                $chainResp = $this->httpClient->request('GET', $chainUrl);
                $chainData = $chainResp->toArray();

                $stage        = -1;
                $hasEvolvesTo = false;
                $chainDepth   = 0;

                $this->traverseChain($chainData['chain'], $searchName, 0, $stage, $hasEvolvesTo, $chainDepth);

                if ($chainDepth === 0) {
                    return 10;
                }

                if (!$hasEvolvesTo) {
                    return 10;
                }

                if ($stage === 0) {
                    return $bst <= 450 ? 5 : 8;
                }

                if ($stage === 1) {
                    return $bst <= 450 ? 7 : 8;
                }

                return 8;
            } catch (\Exception $e) {
                return 10; // fallback seguro
            }
        });
    }

    /**
     * Obter encontros oficiais de um Pokémon na PokeAPI
     */
    public function getPokemonEncounters(string $pokemonName): array
    {
        return $this->cache->get('pokemon_encounters_' . strtolower($pokemonName), function (ItemInterface $item) use ($pokemonName) {
            $item->expiresAfter(86400 * 7); // Cache por 7 dias
            try {
                $response = $this->httpClient->request('GET', 'https://pokeapi.co/api/v2/pokemon/' . strtolower($pokemonName) . '/encounters');
                $data = $response->toArray();

                $encounters = [];
                foreach ($data as $itemEnc) {
                    $areaName = $itemEnc['location_area']['name'];
                    // Formatar nome da area: canalave-city-area -> Canalave City Area
                    $formattedArea = str_replace('-', ' ', $areaName);
                    $formattedArea = ucwords($formattedArea);

                    // Coletar games/versões
                    $versions = [];
                    foreach ($itemEnc['version_details'] as $vd) {
                        $versions[] = ucwords(str_replace('-', ' ', $vd['version']['name']));
                    }

                    $encounters[] = [
                        'area' => $formattedArea,
                        'versions' => implode(', ', $versions),
                    ];
                }
                
                // Sortear por nome da area
                usort($encounters, function($a, $b) {
                    return strcmp($a['area'], $b['area']);
                });

                return $encounters;
            } catch (\Exception $e) {
                return [];
            }
        });
    }

    /**
     * Percorre recursivamente a cadeia evolutiva para obter informações do pokemon alvo.
     */
    private function traverseChain(array $node, string $target, int $depth, int &$stage, bool &$hasEvolvesTo, int &$chainDepth): void
    {
        $name = strtolower($node['species']['name']);
        $evolvesTo = $node['evolves_to'] ?? [];

        if ($depth > $chainDepth) {
            $chainDepth = $depth;
        }

        if ($name === $target) {
            $stage        = $depth;
            $hasEvolvesTo = !empty($evolvesTo);
        }

        foreach ($evolvesTo as $next) {
            $this->traverseChain($next, $target, $depth + 1, $stage, $hasEvolvesTo, $chainDepth);
        }
    }
}
