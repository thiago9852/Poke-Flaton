<?php

namespace App\Service\PokeApi;

class PokeApiValidator
{
    private array $allowedGenerations;
    private array $allowedExtraIds;
    private array $excludedIds;
    private array $megaEvolutions;

    public function __construct(
        array $allowedGenerations,
        array $allowedExtraIds,
        array $excludedIds,
        array $megaEvolutions
    ) {
        $this->allowedGenerations = $allowedGenerations;
        $this->allowedExtraIds = $allowedExtraIds;
        $this->excludedIds = $excludedIds;
        $this->megaEvolutions = $megaEvolutions;
    }

    public function getMegaEvolutions(): array
    {
        return $this->megaEvolutions;
    }

    public static function getGenerationById(int $id): int
    {
        if ($id >= 1 && $id <= 151) return 1;
        if ($id >= 152 && $id <= 251) return 2;
        if ($id >= 252 && $id <= 386) return 3;
        if ($id >= 387 && $id <= 493) return 4;
        if ($id >= 494 && $id <= 649) return 5;
        if ($id >= 650 && $id <= 721) return 6;
        if ($id >= 722 && $id <= 809) return 7;
        if ($id >= 810 && $id <= 905) return 8;
        if ($id >= 906 && $id <= 1025) return 9;
        return 0; // Out of standard range
    }

    public function getBaseSpeciesId(int $id): int
    {
        if ($id < 10000) {
            return $id;
        }
        foreach ($this->megaEvolutions as $baseId => $megas) {
            foreach ($megas as $mega) {
                if ($mega['id'] === $id) {
                    return $baseId;
                }
            }
        }
        return $id;
    }

    public function isPokemonAllowed(int $id): bool
    {
        if ($id >= 10000) {
            $baseId = $this->getBaseSpeciesId($id);
            if ($baseId === $id) {
                // If ID >= 10000 is not mapped to a mega evolution, block it.
                return false;
            }
        } else {
            $baseId = $id;
        }

        // Check if explicitly excluded
        if (in_array($baseId, $this->excludedIds) || in_array($id, $this->excludedIds)) {
            return false;
        }

        // Check if generation is allowed
        $gen = self::getGenerationById($baseId);
        if (in_array($gen, $this->allowedGenerations)) {
            return true;
        }

        // Check if in the extra allowed list
        if (in_array($baseId, $this->allowedExtraIds) || in_array($id, $this->allowedExtraIds)) {
            return true;
        }

        return false;
    }
}
