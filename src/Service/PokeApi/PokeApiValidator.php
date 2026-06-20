<?php

namespace App\Service\PokeApi;

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\PokemonVariation;
use App\Repository\PokemonVariationRepository;

class PokeApiValidator
{
    private array $allowedGenerations;
    private array $allowedExtraIds;
    private array $excludedIds;
    private array $megaEvolutions;
    private array $variations = [];

    public function __construct(
        array $allowedGenerations,
        array $allowedExtraIds,
        array $excludedIds,
        array $megaEvolutions,
        EntityManagerInterface $entityManager,
        PokemonVariationRepository $variationRepository
    ) {
        $this->allowedGenerations = $allowedGenerations;
        $this->allowedExtraIds = $allowedExtraIds;
        $this->excludedIds = $excludedIds;
        $this->megaEvolutions = $megaEvolutions;

        // Auto-criação da tabela e carga inicial (seed)
        try {
            $connection = $entityManager->getConnection();
            $schemaManager = $connection->createSchemaManager();
            if (!$schemaManager->tablesExist(['pokemon_variation'])) {
                $connection->executeStatement('
                    CREATE TABLE pokemon_variation (
                        id INT PRIMARY KEY,
                        base_id INT NOT NULL,
                        name VARCHAR(100) NOT NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ');
            }

            $dbVariations = $variationRepository->findAll();
            if (empty($dbVariations)) {
                $defaultVariations = [
                    10114 => ['base_id' => 103, 'name' => 'exeggutor-alola'],
                    10115 => ['base_id' => 115, 'name' => 'marowak-alola'],
                    10250 => ['base_id' => 128, 'name' => 'tauros-paldea-combat-breed'],
                    10251 => ['base_id' => 128, 'name' => 'tauros-paldea-blaze-breed'],
                    10252 => ['base_id' => 128, 'name' => 'tauros-paldea-aqua-breed'],
                    10233 => ['base_id' => 157, 'name' => 'typhlosion-hisui'],
                    10235 => ['base_id' => 215, 'name' => 'sneasel-hisui'],
                    10184 => ['base_id' => 849, 'name' => 'toxtricity-low-key'],
                    10004 => ['base_id' => 413, 'name' => 'wormadam-sandy'],
                    10005 => ['base_id' => 413, 'name' => 'wormadam-trash'],
                    10016 => ['base_id' => 550, 'name' => 'basculin-blue-striped'],
                    10247 => ['base_id' => 550, 'name' => 'basculin-white-striped'],
                ];

                foreach ($defaultVariations as $id => $data) {
                    $v = new PokemonVariation();
                    $v->setId($id);
                    $v->setBaseId($data['base_id']);
                    $v->setName($data['name']);
                    $entityManager->persist($v);
                }
                $entityManager->flush();

                $dbVariations = $variationRepository->findAll();
            }

            foreach ($dbVariations as $var) {
                $this->variations[$var->getId()] = [
                    'base_id' => $var->getBaseId(),
                    'name' => $var->getName()
                ];
            }
        } catch (\Exception $e) {
            // Em caso de falha de conexão ou migração inicial, mantém a lista limpa ou loga silenciosamente
        }
    }

    public function getMegaEvolutions(): array
    {
        return $this->megaEvolutions;
    }

    public function getVariations(): array
    {
        return $this->variations;
    }

    public function getAllowedGenerations(): array
    {
        return $this->allowedGenerations;
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
        if (isset($this->variations[$id])) {
            return $this->variations[$id]['base_id'];
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
        // Check if explicitly excluded
        if (in_array($id, $this->excludedIds)) {
            return false;
        }

        // Check if in the extra allowed list
        if (in_array($id, $this->allowedExtraIds)) {
            return true;
        }

        if ($id >= 10000) {
            $baseId = $this->getBaseSpeciesId($id);
            if ($baseId === $id) {
                // If ID >= 10000 is not mapped to a mega evolution or variation, block it.
                return false;
            }
        } else {
            $baseId = $id;
        }

        // Check if baseId is explicitly excluded
        if (in_array($baseId, $this->excludedIds)) {
            return false;
        }

        // Check if generation is allowed
        $gen = self::getGenerationById($baseId);
        if (in_array($gen, $this->allowedGenerations)) {
            return true;
        }

        // Check if baseId in the extra allowed list
        if (in_array($baseId, $this->allowedExtraIds)) {
            return true;
        }

        return false;
    }
}
