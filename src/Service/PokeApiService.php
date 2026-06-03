<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class PokeApiService
{
    private HttpClientInterface $httpClient;
    private CacheInterface $cache;

    public function __construct(HttpClientInterface $httpClient, CacheInterface $cache)
    {
        $this->httpClient = $httpClient;
        $this->cache = $cache;
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
                $moves[] = $m['move']['name'];
            }
            sort($moves);

            return [
                'id' => $data['id'],
                'name' => $data['name'],
                'sprite_front' => $data['sprites']['front_default'],
                'sprite_back' => $data['sprites']['back_default'],
                'sprite_front_shiny' => $data['sprites']['front_shiny'],
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
     * Helper para mapear a árvore de evolução
     */
    private function parseEvolutionChain(array $chainNode): array
    {
        $evolutionList = [];
        
        $speciesName = $chainNode['species']['name'];
        $parts = explode('/', rtrim($chainNode['species']['url'], '/'));
        $speciesId = (int) end($parts);
        
        $evolutionList[] = [
            'name' => $speciesName,
            'id' => $speciesId,
            'sprite' => sprintf('https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/%d.png', $speciesId)
        ];
        
        if (!empty($chainNode['evolves_to'])) {
            foreach ($chainNode['evolves_to'] as $nextBranch) {
                $evolutionList = array_merge($evolutionList, $this->parseEvolutionChain($nextBranch));
            }
        }
        
        return $evolutionList;
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
     * Obter lista contendo apenas nomes e IDs para busca/filtro
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
                    'types' => [] // vazio por padrão
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
}

