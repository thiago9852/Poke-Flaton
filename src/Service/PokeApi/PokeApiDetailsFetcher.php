<?php

namespace App\Service\PokeApi;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class PokeApiDetailsFetcher
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

    public function __construct(HttpClientInterface $httpClient, CacheInterface $cache)
    {
        $this->httpClient = $httpClient;
        $this->cache = $cache;
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
     * Obter detalhes de um movimento incluindo quais Pokémon o aprendem
     */
    public function getMoveDetailsWithLearnedBy(string $moveName): array
    {
        return $this->cache->get('move_details_learned_' . $moveName, function (ItemInterface $item) use ($moveName) {
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

                $learnedBy = [];
                foreach ($data['learned_by_pokemon'] as $p) {
                    $parts = explode('/', rtrim($p['url'], '/'));
                    $id = (int) end($parts);
                    $learnedBy[] = [
                        'name' => $p['name'],
                        'id' => $id,
                    ];
                }

                return [
                    'name' => $data['name'],
                    'type' => $typeName,
                    'type_icon' => $typeIcon,
                    'category' => $data['damage_class']['name'],
                    'power' => $data['power'],
                    'accuracy' => $data['accuracy'],
                    'description' => $description ?: 'Sem descrição de efeito.',
                    'learned_by_pokemon' => $learnedBy,
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
                    'description' => 'Golpe físico ou especial.',
                    'learned_by_pokemon' => [],
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
}
