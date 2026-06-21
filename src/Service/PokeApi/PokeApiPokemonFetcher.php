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

    public static function resolveNameAlias(string $name): string
    {
        $name = strtolower(trim($name));
        $map = [
            'burmy' => 'burmy-plant',
            'wormadam' => 'wormadam-plant',
            'toxtricity' => 'toxtricity-amped',
            'mimikyu' => 'mimikyu-disguised',
            'basculin' => 'basculin-red-striped',
        ];
        return $map[$name] ?? $name;
    }

    public static function getPokeApiName(string $canonicalName): string
    {
        $canonicalName = strtolower(trim($canonicalName));
        $map = [
            'burmy-plant' => 'burmy',
        ];
        return $map[$canonicalName] ?? $canonicalName;
    }

    public static function getSpeciesName(string $name): string
    {
        $name = strtolower(trim($name));
        $suffixes = [
            '-mega', '-mega-x', '-mega-y', '-mega-z',
            '-alola', '-galar', '-hisui', '-paldea',
            '-combat-breed', '-blaze-breed', '-aqua-breed',
            '-gmax', '-amped', '-low-key',
            '-red-striped', '-white-striped', '-blue-striped',
            '-disguised', '-busted'
        ];
        
        foreach ($suffixes as $suffix) {
            if (str_ends_with($name, $suffix)) {
                return substr($name, 0, -strlen($suffix));
            }
        }
        
        $map = [
            'burmy-plant' => 'burmy',
            'burmy-sandy' => 'burmy',
            'burmy-trash' => 'burmy',
            'wormadam-plant' => 'wormadam',
            'wormadam-sandy' => 'wormadam',
            'wormadam-trash' => 'wormadam',
        ];
        
        return $map[$name] ?? $name;
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
        return $this->cache->get('pokemon_type_v2_' . $type . '_' . $limit . '_' . $offset, function (ItemInterface $item) use ($type, $limit, $offset) {
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
                    'name' => self::resolveNameAlias($pokemonName),
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
        $canonicalNameOrId = is_numeric($nameOrId) ? $nameOrId : self::resolveNameAlias($nameOrId);
        $cacheKey = 'pokemon_details_v5_' . strtolower($canonicalNameOrId);
        $details = $this->cache->get($cacheKey, function (ItemInterface $item) use ($canonicalNameOrId) {
            $item->expiresAfter(86400 * 7); // Cache por 7 dias
            return $this->fetchPokemonDetailsRaw($canonicalNameOrId);
        });

        // Se o cache for antigo e não contiver os novos campos, força a re-busca
        if (!isset($details['default_variety_name'])) {
            $this->cache->delete($cacheKey);
            $details = $this->cache->get($cacheKey, function (ItemInterface $item) use ($canonicalNameOrId) {
                $item->expiresAfter(86400 * 7);
                return $this->fetchPokemonDetailsRaw($canonicalNameOrId);
            });
        }

        return $details;
    }

    /**
     * Busca os detalhes reais do Pokémon na API
     */
    private function fetchPokemonDetailsRaw(string $nameOrId): array
    {
        $apiNameOrId = is_numeric($nameOrId) ? $nameOrId : self::getPokeApiName($nameOrId);
        $response = $this->httpClient->request('GET', 'https://pokeapi.co/api/v2/pokemon/' . strtolower($apiNameOrId));
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

        if (empty($moves)) {
            try {
                $baseResponse = $this->httpClient->request('GET', 'https://pokeapi.co/api/v2/pokemon/' . $speciesId);
                $baseData = $baseResponse->toArray();
                foreach ($baseData['moves'] as $m) {
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
            } catch (\Exception $e) {
                // ignore
            }
        }

        // Fetch variedade de especies
        $speciesData = $this->cache->get('pokemon_species_' . $speciesId, function (ItemInterface $item) use ($speciesUrl) {
            $item->expiresAfter(86400 * 7);
            $resp = $this->httpClient->request('GET', $speciesUrl);
            return $resp->toArray();
        });

        $varieties = [];
        foreach ($speciesData['varieties'] as $v) {
            $vName = $v['pokemon']['name'];
            $vUrl = $v['pokemon']['url'];
            $vParts = explode('/', rtrim($vUrl, '/'));
            $vId = (int) end($vParts);

            if ($v['is_default']) {
                continue;
            }

            if ($this->validator->isPokemonAllowed($vId)) {
                if (str_contains($vName, '-mega') || str_contains($vName, '-z')) {
                    $varieties[] = [
                        'name' => $vName,
                        'display_name' => str_replace(['-x', '-y', '-mega', '-z'], [' X', ' Y', ' Mega', ' Z'], $vName)
                    ];
                } else {
                    $displayName = $vName;
                    if (str_starts_with($vName, $speciesName . '-')) {
                        $suffix = substr($vName, strlen($speciesName) + 1);
                        $displayName = str_replace('-', ' ', $suffix);
                    }
                    $varieties[] = [
                        'name' => $vName,
                        'display_name' => ucwords($displayName)
                    ];
                }
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

        // Determinar o nome de exibição da forma padrão
        $defaultVarietyName = $speciesName;
        foreach ($speciesData['varieties'] as $v) {
            if ($v['is_default']) {
                $defaultVarietyName = $v['pokemon']['name'];
                break;
            }
        }

        $canonicalDefaultVarietyName = self::resolveNameAlias($defaultVarietyName);
        $canonicalName = self::resolveNameAlias($data['name']);

        $defaultDisplayName = 'Padrão';
        if ($canonicalDefaultVarietyName === 'burmy-plant') {
            $defaultDisplayName = 'Plant';
        } elseif ($canonicalDefaultVarietyName === 'wormadam-plant') {
            $defaultDisplayName = 'Plant';
        } elseif ($canonicalDefaultVarietyName === 'toxtricity-amped') {
            $defaultDisplayName = 'Amped';
        } elseif ($canonicalDefaultVarietyName === 'mimikyu-disguised') {
            $defaultDisplayName = 'Disguised';
        } elseif ($canonicalDefaultVarietyName === 'basculin-red-striped') {
            $defaultDisplayName = 'Red Striped';
        } elseif ($canonicalDefaultVarietyName !== $speciesName) {
            if (str_starts_with($canonicalDefaultVarietyName, $speciesName . '-')) {
                $suffix = substr($canonicalDefaultVarietyName, strlen($speciesName) + 1);
                $defaultDisplayName = ucwords(str_replace('-', ' ', $suffix));
            }
        }

        return [
            'id' => $data['id'],
            'name' => $canonicalName,
            'species_id' => $speciesId,
            'species_name' => $speciesName,
            'default_variety_name' => $canonicalDefaultVarietyName,
            'default_display_name' => $defaultDisplayName,
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
        return $this->cache->get('pokemon_basic_list_configured_v7', function (ItemInterface $item) {
            $item->expiresAfter(86400 * 30); // 30 dias
            $response = $this->httpClient->request('GET', 'https://pokeapi.co/api/v2/pokemon?limit=2000');
            $data = $response->toArray();

            $list = [];
            foreach ($data['results'] as $pokemon) {
                $parts = explode('/', rtrim($pokemon['url'], '/'));
                $id = (int) end($parts);

                // Ignorar variedades (IDs >= 10000) no basic list base, exceto se configurado em variações
                if ($id >= 10000 && !isset($this->validator->getVariations()[$id])) {
                    continue;
                }

                if (!$this->validator->isPokemonAllowed($id)) {
                    continue;
                }
                $list[] = [
                    'id' => $id,
                    'name' => self::resolveNameAlias($pokemon['name']),
                    'sprite' => sprintf('https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/%d.png', $id),
                    'types' => [], // vazio por padrão para velocidade
                    'dex_id' => $this->validator->getBaseSpeciesId($id)
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
                    'name' => self::resolveNameAlias($pokemon['name']),
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
        return $this->cache->get('pokemon_basic_list_type_v4_' . strtolower($type), function (ItemInterface $item) use ($type) {
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
                    'name' => self::resolveNameAlias($p['pokemon']['name']),
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
            $apiName = self::getPokeApiName($p['name']);
            $responses[$p['name']] = $this->httpClient->request('GET', 'https://pokeapi.co/api/v2/pokemon/' . $apiName);
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
            $canonicalName = self::resolveNameAlias($name);
            
            // 1. Verificar se o cache do lote (light) existe
            $batchCacheKey = 'pokemon_details_batch_' . $canonicalName;
            $batchItem = $this->cache->getItem($batchCacheKey);
            if ($batchItem->isHit()) {
                $detailsList[$canonicalName] = $batchItem->get();
                continue;
            }

            // 2. Alternativa: Se o cache detalhado completo existir, podemos usá-lo!
            $fullCacheKey = 'pokemon_details_v3_' . $canonicalName;
            $fullItem = $this->cache->getItem($fullCacheKey);
            if ($fullItem->isHit()) {
                $detailsList[$canonicalName] = $fullItem->get();
                continue;
            }

            // 3. Caso contrário, precisamos buscar na API
            $missedNames[] = $canonicalName;
        }

        if (!empty($missedNames)) {
            $responses = [];
            foreach ($missedNames as $name) {
                $apiName = self::getPokeApiName($name);
                $responses[$name] = $this->httpClient->request('GET', 'https://pokeapi.co/api/v2/pokemon/' . $apiName);
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
                        'name' => $name, // canonical name
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
    public function getPokemonEvolutionChain(string $pokemonName, ?array $currentPokemon = null): array
    {
        $canonicalName = self::resolveNameAlias($pokemonName);
        $cacheSuffix = $currentPokemon ? '_' . $currentPokemon['id'] : '';
        return $this->cache->get('evolution_chain_v7_for_' . $canonicalName . $cacheSuffix, function (ItemInterface $item) use ($canonicalName, $currentPokemon) {
            $item->expiresAfter(86400 * 30); // 30 dias
            try {
                $apiName = self::getSpeciesName($canonicalName);
                // 1. Busca espécie para obter a URL da cadeia evolutiva
                $response = $this->httpClient->request('GET', 'https://pokeapi.co/api/v2/pokemon-species/' . strtolower($apiName));
                $speciesData = $response->toArray();

                $chainUrl = $speciesData['evolution_chain']['url'];

                // 2. Busca os dados da cadeia evolutiva
                $chainResponse = $this->httpClient->request('GET', $chainUrl);
                $chainData = $chainResponse->toArray();

                // 3. Builda floresta de variação
                $rootNodes = $this->buildVarietyForest($chainData['chain'], 1, []);

                // 4. Busca nodo
                $targetName = $currentPokemon ? $currentPokemon['name'] : $canonicalName;
                $targetNode = $this->findTargetNode($rootNodes, $targetName);
                if (!$targetNode && $currentPokemon) {
                    $targetNode = $this->findTargetNodeFallback($rootNodes, $currentPokemon['id'], $currentPokemon['species_name']);
                }

                // 5. Marca o alvo, antecessor e descendente
                if ($targetNode) {
                    $targetNode->isTarget = true;
                    
                    $p = $targetNode->parent;
                    while ($p !== null) {
                        $p->isAncestor = true;
                        $p = $p->parent;
                    }
                    
                    $this->markDescendants($targetNode);
                } else {
                    // Fallback
                    foreach ($rootNodes as $rn) {
                        if ($rn->isDefault) {
                            $rn->isTarget = true;
                            $this->markDescendants($rn);
                        }
                    }
                }

                // 6. Pega nodo e o grupo
                $stages = [];
                $this->collectKeptNodes($rootNodes, $stages);
                
                ksort($stages);
                return $stages;
            } catch (\Exception $e) {
                return [];
            }
        });
    }

    private function getFormSuffix(string $name): ?string
    {
        $suffixes = ['alola', 'galar', 'hisui', 'paldea'];
        foreach ($suffixes as $suffix) {
            if (str_contains($name, '-' . $suffix)) {
                return $suffix;
            }
        }
        return null;
    }

    private function isCompatibleTransition(
        string $parentVarietyName,
        string $childVarietyName,
        array $evolutionDetails,
        array $parentVarietiesInfo
    ): bool {
        if (empty($evolutionDetails)) {
            foreach ($parentVarietiesInfo as $pv) {
                if ($pv['name'] === $parentVarietyName) {
                    return $pv['is_default'];
                }
            }
            return false;
        }

        foreach ($evolutionDetails as $detail) {
            if (!empty($detail['base_form']['name'])) {
                if ($parentVarietyName === $detail['base_form']['name']) {
                    return true;
                }
            } else {
                // base_form is null/empty. This evolves from the default parent variety.
                $parentIsDefault = false;
                foreach ($parentVarietiesInfo as $pv) {
                    if ($pv['name'] === $parentVarietyName && $pv['is_default']) {
                        $parentIsDefault = true;
                        break;
                    }
                }

                if ($parentIsDefault) {
                    // Disambiguate when base_form is null by checking suffixes (aligning standard with standard, regional with regional)
                    $childSuffix = $this->getFormSuffix($childVarietyName);
                    $parentSuffix = $this->getFormSuffix($parentVarietyName);
                    if ($childSuffix === $parentSuffix) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function buildVarietyForest(array $chainNode, int $stage = 1, array $parentNodes = []): array
    {
        $speciesName = $chainNode['species']['name'];
        $parts = explode('/', rtrim($chainNode['species']['url'], '/'));
        $speciesId = (int) end($parts);

        $speciesUrl = $chainNode['species']['url'];
        try {
            $speciesData = $this->cache->get('pokemon_species_' . $speciesId, function (ItemInterface $item) use ($speciesUrl) {
                $item->expiresAfter(86400 * 7);
                $resp = $this->httpClient->request('GET', $speciesUrl);
                return $resp->toArray();
            });
        } catch (\Exception $e) {
            return [];
        }

        $varietiesInfo = [];
        foreach ($speciesData['varieties'] as $v) {
            $vName = $v['pokemon']['name'];
            $vUrl = $v['pokemon']['url'];
            $vParts = explode('/', rtrim($vUrl, '/'));
            $vId = (int) end($vParts);

            if (str_contains($vName, '-mega') || str_contains($vName, '-gmax') || str_contains($vName, '-totem') || str_contains($vName, '-primal')) {
                continue;
            }

            if ($this->validator->isPokemonAllowed($vId)) {
                $varietiesInfo[] = [
                    'name' => self::resolveNameAlias($vName),
                    'id' => $vId,
                    'is_default' => $v['is_default']
                ];
            }
        }

        if (empty($varietiesInfo)) {
            $defaultVarietyName = $speciesName;
            $defaultVarietyId = $speciesId;
            foreach ($speciesData['varieties'] as $v) {
                if ($v['is_default']) {
                    $defaultVarietyName = $v['pokemon']['name'];
                    $vParts = explode('/', rtrim($v['pokemon']['url'], '/'));
                    $defaultVarietyId = (int) end($vParts);
                    break;
                }
            }
            $varietiesInfo[] = [
                'name' => self::resolveNameAlias($defaultVarietyName),
                'id' => $defaultVarietyId,
                'is_default' => true
            ];
        }

        $currentLevelNodes = [];
        foreach ($varietiesInfo as $var) {
            try {
                $details = $this->getPokemonDetails($var['name']);
                $types = $details['types'];
                $sprite = $details['sprite_official'];
            } catch (\Exception $e) {
                $types = ['normal'];
                $sprite = sprintf('https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/%d.png', $var['id']);
            }

            $node = new \stdClass();
            $node->name = $var['name'];
            $node->id = $var['id'];
            $node->types = $types;
            $node->sprite = $sprite;
            $node->evolution_method = null;
            $node->evolution_gender = null;
            $node->children = [];
            $node->parent = null;
            $node->stage = $stage;
            $node->isTarget = false;
            $node->isAncestor = false;
            $node->isDescendant = false;
            $node->isDefault = $var['is_default'];

            if ($stage > 1 && !empty($parentNodes)) {
                foreach ($parentNodes as $parent) {
                    $parentVarietiesInfo = [];
                    foreach ($parentNodes as $pn) {
                        $parentVarietiesInfo[] = [
                            'name' => $pn->name,
                            'is_default' => $pn->isDefault
                        ];
                    }

                    if ($this->isCompatibleTransition($parent->name, $node->name, $chainNode['evolution_details'], $parentVarietiesInfo)) {
                        $cleanParent = explode('-', $parent->name)[0];
                        $cleanChild = explode('-', $node->name)[0];
                        $rule = $this->evolutionRuleRepository->findOneBy([
                            'basePokemon' => strtolower($cleanParent),
                            'evolvedPokemon' => strtolower($cleanChild)
                        ]);

                        $node->evolution_method = $rule ? $rule->getMethod() : 'Nível/Stone';
                        $node->evolution_gender = $rule ? $rule->getGender() : null;
                        $node->parent = $parent;
                        $parent->children[] = $node;
                    }
                }
            }

            $currentLevelNodes[] = $node;
        }

        if (!empty($chainNode['evolves_to'])) {
            foreach ($chainNode['evolves_to'] as $nextBranch) {
                $this->buildVarietyForest($nextBranch, $stage + 1, $currentLevelNodes);
            }
        }

        return $currentLevelNodes;
    }

    private function findTargetNode(array $nodes, string $targetName)
    {
        foreach ($nodes as $node) {
            if ($node->name === $targetName) {
                return $node;
            }
            $found = $this->findTargetNode($node->children, $targetName);
            if ($found) {
                return $found;
            }
        }
        return null;
    }

    private function findTargetNodeFallback(array $nodes, int $targetId, string $targetSpeciesName)
    {
        foreach ($nodes as $node) {
            $nodeCleanName = explode('-', $node->name)[0];
            $targetCleanName = explode('-', $targetSpeciesName)[0];
            if ($node->id === $targetId || $nodeCleanName === $targetCleanName) {
                return $node;
            }
            $found = $this->findTargetNodeFallback($node->children, $targetId, $targetSpeciesName);
            if ($found) {
                return $found;
            }
        }
        return null;
    }

    private function markDescendants($node)
    {
        foreach ($node->children as $child) {
            $child->isDescendant = true;
            $this->markDescendants($child);
        }
    }

    private function collectKeptNodes(array $nodes, array &$stages)
    {
        foreach ($nodes as $node) {
            $isParentAncestorOrTarget = false;
            if ($node->parent !== null) {
                if ($node->parent->isAncestor || $node->parent->isTarget) {
                    $isParentAncestorOrTarget = true;
                }
            }

            $keep = $node->isTarget || $node->isAncestor || $node->isDescendant || $isParentAncestorOrTarget;

            if ($keep) {
                if (!isset($stages[$node->stage])) {
                    $stages[$node->stage] = [];
                }
                $exists = false;
                foreach ($stages[$node->stage] as $existing) {
                    if ($existing['id'] === $node->id) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $stages[$node->stage][] = [
                        'name' => $node->name,
                        'id' => $node->id,
                        'sprite' => $node->sprite,
                        'types' => $node->types,
                        'evolution_method' => $node->evolution_method,
                        'evolution_gender' => $node->evolution_gender,
                    ];
                }
            }

            $this->collectKeptNodes($node->children, $stages);
        }
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
