<?php

declare(strict_types=1);

namespace App\Service;

use App\Service\PokeApi\PokeApiDetailsFetcher;
use App\Service\PokeApi\PokeApiPokemonFetcher;
use App\Service\PokeApi\PokeApiValidator;

class PokeApiService
{
    public function __construct(private readonly PokeApiValidator $validator, private readonly PokeApiDetailsFetcher $detailsFetcher, private readonly PokeApiPokemonFetcher $pokemonFetcher)
    {
    }

    public function getMegaEvolutions(): array
    {
        return $this->validator->getMegaEvolutions();
    }

    public function getAllowedGenerations(): array
    {
        return $this->validator->getAllowedGenerations();
    }

    public function isPokemonAllowed(int $id): bool
    {
        return $this->validator->isPokemonAllowed($id);
    }

    public function getBaseSpeciesId(int $id): int
    {
        return $this->validator->getBaseSpeciesId($id);
    }

    public static function getGenerationById(int $id): int
    {
        return PokeApiValidator::getGenerationById($id);
    }

    public function getPokemonList(int $limit = 24, int $offset = 0): array
    {
        return $this->pokemonFetcher->getPokemonList($limit, $offset);
    }

    public function getPokemonByType(string $type, int $limit = 24, int $offset = 0): array
    {
        return $this->pokemonFetcher->getPokemonByType($type, $limit, $offset);
    }

    public function getPokemonDetails(string $nameOrId): array
    {
        return $this->pokemonFetcher->getPokemonDetails($nameOrId);
    }

    public function getPokemonBasicList(): array
    {
        return $this->pokemonFetcher->getPokemonBasicList();
    }

    public function clearBasicListCache(): void
    {
        $this->pokemonFetcher->clearBasicListCache();
    }

    public function getPokemonGameList(): array
    {
        return $this->pokemonFetcher->getPokemonGameList();
    }

    public function getPokemonBasicListByType(string $type): array
    {
        return $this->pokemonFetcher->getPokemonBasicListByType($type);
    }

    public function getPokemonDetailsBatch(array $pokemonBasicList): array
    {
        return $this->pokemonFetcher->getPokemonDetailsBatch($pokemonBasicList);
    }

    public function getPokemonDetailsBatchByNames(array $names): array
    {
        return $this->pokemonFetcher->getPokemonDetailsBatchByNames($names);
    }

    public function getPokemonEvolutionChain(string $pokemonName, ?array $currentPokemon = null): array
    {
        return $this->pokemonFetcher->getPokemonEvolutionChain($pokemonName, $currentPokemon);
    }

    public function calculateMaxMoves(string $pokemonName, array $stats): int
    {
        return $this->pokemonFetcher->calculateMaxMoves($pokemonName, $stats);
    }

    public function getMoveDetails(string $moveName): array
    {
        return $this->detailsFetcher->getMoveDetails($moveName);
    }

    public function getMoveDetailsWithLearnedBy(string $moveName): array
    {
        return $this->detailsFetcher->getMoveDetailsWithLearnedBy($moveName);
    }

    public function getAbilityDetails(string $abilityName): array
    {
        return $this->detailsFetcher->getAbilityDetails($abilityName);
    }

    public function getItemDetails(string $itemName): array
    {
        return $this->detailsFetcher->getItemDetails($itemName);
    }

    public function getTypeDetails(string $typeName): array
    {
        return $this->detailsFetcher->getTypeDetails($typeName);
    }

    public function getNatures(): array
    {
        return $this->detailsFetcher->getNatures();
    }

    public function getItems(): array
    {
        return $this->detailsFetcher->getItems();
    }

    public function getPokemonEncounters(string $pokemonName): array
    {
        return $this->pokemonFetcher->getPokemonEncounters($pokemonName);
    }
}
