<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use App\Repository\EvolutionRuleRepository;

class PokeApiService
{
    private const TYPE_IDS = [
        'normal' => 1,
        'fighting' => 2,
        'flying' => 3,
        'poison' => 4,
        'ground' => 5,
        'rock' => 6,
        'bug' => 7,
        'ghost' => 8,
        'steel' => 9,
        'fire' => 10,
        'water' => 11,
        'grass' => 12,
        'electric' => 13,
        'psychic' => 14,
        'ice' => 15,
        'dragon' => 16,
        'dark' => 17,
        'fairy' => 18,
        'stellar' => 19,
        'unknown' => 10001,
        'shadow' => 10002,
    ];

    private HttpClientInterface $httpClient;
    private CacheInterface $cache;
    private EvolutionRuleRepository $evolutionRuleRepository;

    public function __construct(
        HttpClientInterface $httpClient, 
        CacheInterface $cache,
        EvolutionRuleRepository $evolutionRuleRepository
    ) {
        $this->httpClient = $httpClient;
        $this->cache = $cache;
        $this->evolutionRuleRepository = $evolutionRuleRepository;
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

            // 1. Busca todos os pokemons daquele tipo (requisição rápida e leve)
            $response = $this->httpClient->request('GET', 'https://pokeapi.co/api/v2/type/' . strtolower($type));
            $data = $response->toArray();

            $allPokemonOfType = $data['pokemon'];
            $totalCount = count($allPokemonOfType);

            // 2. Aplica o slice para a página atual
            $pagedPokemon = array_slice($allPokemonOfType, $offset, $limit);

            // 3. Faz requisições paralelas para os detalhes de apenas esses $limit pokemons
            $responses = [];
            foreach ($pagedPokemon as $p) {
                $pokemonName = $p['pokemon']['name'];
                $responses[$pokemonName] = $this->httpClient->request('GET', $p['pokemon']['url']);
            }

            $list = [];
            foreach ($pagedPokemon as $p) {
                $pokemonName = $p['pokemon']['name'];
                $id = null;
                $types = [];
                try {
                    $details = $responses[$pokemonName]->toArray();
                    $id = $details['id'];
                    foreach ($details['types'] as $t) {
                        $types[] = $t['type']['name'];
                    }
                } catch (\Exception $e) {
                    $parts = explode('/', rtrim($p['pokemon']['url'], '/'));
                    $id = (int) end($parts);
                }

                $list[] = [
                    'id' => $id,
                    'name' => $pokemonName,
                    'sprite' => sprintf('https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/%d.png', $id),
                    'types' => $types
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
        return $this->cache->get('pokemon_details_' . strtolower($nameOrId), function (ItemInterface $item) use ($nameOrId) {
            $item->expiresAfter(86400 * 7); // Cache por 7 dias

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

            return [
                'id' => $data['id'],
                'name' => $data['name'],
                'sprite_official' => sprintf('https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/%d.png', $data['id']),
                'types' => $types,
                'stats' => $stats,
                'drops' => $heldItems,
                'moves' => $moves,
                'abilities' => $abilities
            ];
        });
    }


    /**
     * Obter detalhes de uma habilidade
     */
    public function getAbilityDetails(string $abilityName): array
    {
        return $this->cache->get('ability_' . $abilityName, function (ItemInterface $item) use ($abilityName) {
            $item->expiresAfter(86400 * 30); // 30 dias

            try {
                $response = $this->httpClient->request('GET', 'https://pokeapi.co/api/v2/ability/' . strtolower($abilityName));
                $data = $response->toArray();

                $description = '';
                if (!empty($data['flavor_text_entries'])) {
                    foreach ($data['flavor_text_entries'] as $entry) {
                        if ($entry['language']['name'] === 'en') {
                            $description = str_replace(["\n", "\f", "\r"], ' ', $entry['flavor_text']);
                            break;
                        }
                    }
                }
                
                if (empty($description) && !empty($data['effect_entries'])) {
                    foreach ($data['effect_entries'] as $entry) {
                        if ($entry['language']['name'] === 'en') {
                            $description = $entry['short_effect'] ?? $entry['effect'];
                            break;
                        }
                    }
                }

                return [
                    'name' => $data['name'],
                    'description' => $description ?: 'Nenhuma descrição disponível.',
                ];
            } catch (\Exception $e) {
                return [
                    'name' => $abilityName,
                    'description' => 'Habilidade recomendada para ativar a estratégia.',
                ];
            }
        });
    }


    /**
     * Obter detalhes de um item
     */
    public function getItemDetails(string $itemName): array
    {
        return $this->cache->get('item_' . $itemName, function (ItemInterface $item) use ($itemName) {
            $item->expiresAfter(86400 * 30); // 30 dias

            try {
                $response = $this->httpClient->request('GET', 'https://pokeapi.co/api/v2/item/' . strtolower($itemName));
                $data = $response->toArray();

                $description = '';
                if (!empty($data['flavor_text_entries'])) {
                    foreach ($data['flavor_text_entries'] as $entry) {
                        if ($entry['language']['name'] === 'en') {
                            $description = str_replace(["\n", "\f", "\r"], ' ', $entry['text']);
                            break;
                        }
                    }
                }
                
                if (empty($description) && !empty($data['effect_entries'])) {
                    foreach ($data['effect_entries'] as $entry) {
                        if ($entry['language']['name'] === 'en') {
                            $description = $entry['short_effect'] ?? $entry['effect'];
                            break;
                        }
                    }
                }

                return [
                    'name' => $itemName,
                    'description' => $description ?: 'Nenhuma descrição disponível.',
                ];
            } catch (\Exception $e) {
                return [
                    'name' => $itemName,
                    'description' => 'Item recomendado para ativar a estratégia.',
                ];
            }
        });
    }


    /**
     * Obter detalhes de um tipo (vantagens e fraquezas)
     */
    public function getTypeDetails(string $typeName): array
    {
        return $this->cache->get('type_details_' . $typeName, function (ItemInterface $item) use ($typeName) {
            $item->expiresAfter(86400 * 30); // 30 dias

            try {
                $response = $this->httpClient->request('GET', 'https://pokeapi.co/api/v2/type/' . strtolower($typeName));
                $data = $response->toArray();

                return [
                    'name' => $typeName,
                    'damage_relations' => $data['damage_relations']
                ];
            } catch (\Exception $e) {
                return [
                    'name' => $typeName,
                    'damage_relations' => []
                ];
            }
        });
    }


    /**
     * Obter a linha evolutiva completa do Pokémon
     */
    public function getPokemonEvolutionChain(string $pokemonName): array
    {
        return $this->cache->get('evolution_chain_for_' . $pokemonName, function (ItemInterface $item) use ($pokemonName) {
            $item->expiresAfter(86400 * 30); // 30 dias
            try {
                // 1. Busca espécie para obter a URL da cadeia evolutiva
                $response = $this->httpClient->request('GET', 'https://pokeapi.co/api/v2/pokemon-species/' . strtolower($pokemonName));
                $speciesData = $response->toArray();
                
                $chainUrl = $speciesData['evolution_chain']['url'];
                
                // 2. Busca os dados da cadeia evolutiva
                $chainResponse = $this->httpClient->request('GET', $chainUrl);
                $chainData = $chainResponse->toArray();
                
                return $this->parseEvolutionChain($chainData['chain']);
            } catch (\Exception $e) {
                return [];
            }
        });
    }

    /**
     * Helper recursivo para mapear a árvore de evolução
     */
    private function parseEvolutionChain(array $chainNode): array
    {
        $evolutionList = [];
        
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
        
        $evolutionList[] = [
            'name' => $speciesName,
            'id' => $speciesId,
            'sprite' => sprintf('https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/%d.png', $speciesId),
            'types' => $types
        ];
        
        if (!empty($chainNode['evolves_to'])) {
            foreach ($chainNode['evolves_to'] as $nextBranch) {
                $nextSpeciesName = $nextBranch['species']['name'];
                
                // Busca a regra de evolução customizada
                $rule = $this->evolutionRuleRepository->findOneBy([
                    'basePokemon' => strtolower($speciesName),
                    'evolvedPokemon' => strtolower($nextSpeciesName)
                ]);
                
                $method = $rule ? $rule->getMethod() : 'Nível/Stone';
                
                // Adiciona a informação ao nó atual (para indicar como ELE evolui para o próximo)
                $evolutionList[count($evolutionList) - 1]['evolves_to_next'] = true;
                $evolutionList[count($evolutionList) - 1]['evolution_method'] = $method;
                
                $evolutionList = array_merge($evolutionList, $this->parseEvolutionChain($nextBranch));
            }
        }
        
        return $evolutionList;
    }


    /**
     * Obter detalhes de um movimento (Tipo, categoria, poder, descrição)
     */
    public function getMoveDetails(string $moveName): array
    {
        return $this->cache->get('move_details_' . $moveName, function (ItemInterface $item) use ($moveName) {
            $item->expiresAfter(86400 * 30); // Cache por 30 dias
            try {
                $response = $this->httpClient->request('GET', 'https://pokeapi.co/api/v2/move/' . $moveName);
                $data = $response->toArray();

                $description = '';
                foreach ($data['effect_entries'] as $ee) {
                    if ($ee['language']['name'] === 'en') {
                        $description = $ee['short_effect'];
                        break;
                    }
                }

                $typeName = $data['type']['name'];
                $typeId = self::TYPE_IDS[$typeName] ?? 1;
                $typeIcon = sprintf(
                    'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/types/generation-ix/scarlet-violet/small/%d.png',
                    $typeId
                );

                return [
                    'name' => $data['name'],
                    'type' => $typeName,
                    'type_icon' => $typeIcon,
                    'category' => $data['damage_class']['name'], // physical, special, status
                    'power' => $data['power'],
                    'accuracy' => $data['accuracy'],
                    'description' => $description ?: 'Sem descrição de efeito.',
                ];
            } catch (\Exception $e) {
                $typeId = self::TYPE_IDS['normal'] ?? 1;
                $typeIcon = sprintf(
                    'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/types/generation-ix/scarlet-violet/small/%d.png',
                    $typeId
                );
                return [
                    'name' => $moveName,
                    'type' => 'normal',
                    'type_icon' => $typeIcon,
                    'category' => 'physical',
                    'power' => null,
                    'accuracy' => null,
                    'description' => 'Golpe físico ou especial.'
                ];
            }
        });
    }

    /**
     * Obter todas as natures
     */
    public function getNatures(): array
    {
        return $this->cache->get('natures_list', function (ItemInterface $item) {
            $item->expiresAfter(86400 * 30); // 30 dias
            $response = $this->httpClient->request('GET', 'https://pokeapi.co/api/v2/nature?limit=30');
            $data = $response->toArray();

            $responses = [];
            foreach ($data['results'] as $nature) {
                $responses[$nature['name']] = $this->httpClient->request('GET', $nature['url']);
            }

            $natures = [];
            foreach ($data['results'] as $nature) {
                $name = $nature['name'];
                try {
                    $nd = $responses[$name]->toArray();
                    $natures[] = [
                        'name' => $name,
                        'increased' => $nd['increased_stat']['name'] ?? 'none',
                        'decreased' => $nd['decreased_stat']['name'] ?? 'none'
                    ];
                } catch (\Exception $e) {
                    $natures[] = [
                        'name' => $name,
                        'increased' => 'none',
                        'decreased' => 'none'
                    ];
                }
            }
            usort($natures, fn($a, $b) => strcmp($a['name'], $b['name']));
            return $natures;
        });
    }

    /**
     * Obter lista de itens seguráveis
     */
    public function getItems(): array
    {
        return $this->cache->get('items_list', function (ItemInterface $item) {
            $item->expiresAfter(86400 * 30); // 30 dias
            $response = $this->httpClient->request('GET', 'https://pokeapi.co/api/v2/item?limit=1000');
            $data = $response->toArray();

            $items = [];
            foreach ($data['results'] as $r) {
                $items[] = [
                    'name' => $r['name'],
                    'sprite' => sprintf('https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/items/%s.png', $r['name'])
                ];
            }
            usort($items, fn($a, $b) => strcmp($a['name'], $b['name']));
            return $items;
        });
    }

    /**
     * Obter lista leve contendo apenas nomes e IDs para busca/filtro rápido
     */
    public function getPokemonBasicList(int $limit = 151): array
    {
        return $this->cache->get('pokemon_basic_list_' . $limit, function (ItemInterface $item) use ($limit) {
            $item->expiresAfter(86400 * 30); // 30 dias
            $response = $this->httpClient->request('GET', 'https://pokeapi.co/api/v2/pokemon?limit=' . $limit);
            $data = $response->toArray();

            $list = [];
            foreach ($data['results'] as $pokemon) {
                $parts = explode('/', rtrim($pokemon['url'], '/'));
                $id = (int) end($parts);
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
                'types' => $types
            ];
        }
        return $list;
    }

    /**
     * Calcular o limite máximo de moves com base no estágio evolutivo e BST
     *
     * Estágios:
     *  - Estágio único (sem evolução): 10 moves
     *  - Estágio final (não evolui mais): 10 moves
     *  - 1ª forma (evolui mais):  BST <= 450 => 5,  BST > 450 => 8
     *  - 2ª forma (evolui mais):  BST <= 450 => 7,  BST > 450 => 8
     */
    public function calculateMaxMoves(string $pokemonName, array $stats): int
    {
        return $this->cache->get('max_moves_' . strtolower($pokemonName), function (ItemInterface $item) use ($pokemonName, $stats) {
            $item->expiresAfter(86400 * 30);

            $bst = array_sum($stats);

            try {
                // Busca a espécie para a cadeia evolutiva
                $speciesResp = $this->httpClient->request('GET', 'https://pokeapi.co/api/v2/pokemon-species/' . strtolower($pokemonName));
                $speciesData = $speciesResp->toArray();

                $chainUrl = $speciesData['evolution_chain']['url'];
                $chainResp = $this->httpClient->request('GET', $chainUrl);
                $chainData = $chainResp->toArray();

                // Percorre a cadeia para descobrir:
                // - quantos estágios tem a cadeia
                // - em qual estágio (0-indexed) o pokemon atual está
                // - se o pokemon atual ainda tem evoluções
                $stage        = -1;
                $hasEvolvesTo = false;
                $chainDepth   = 0;

                $this->traverseChain($chainData['chain'], strtolower($pokemonName), 0, $stage, $hasEvolvesTo, $chainDepth);

                // Estágio único: cadeia de profundidade 0 (nenhuma evolução)
                if ($chainDepth === 0) {
                    return 10;
                }

                // Estágio final (não evolui mais)
                if (!$hasEvolvesTo) {
                    return 10;
                }

                // 1ª forma (stage 0) com mais evoluções
                if ($stage === 0) {
                    return $bst <= 450 ? 5 : 8;
                }

                // 2ª forma (stage 1) com mais evoluções
                if ($stage === 1) {
                    return $bst <= 450 ? 7 : 8;
                }

                // Qualquer forma mais avançada com evoluções => 8
                return 8;

            } catch (\Exception $e) {
                return 10; // fallback seguro
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

        // Atualiza profundidade máxima
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
