<?php

namespace App\Service\PokeApi;

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\PokemonVariation;
use App\Repository\PokemonVariationRepository;
use App\Config\PokemonConfig;

class PokeApiValidator
{
    private array $allowedGenerations;
    private array $allowedExtraIds;
    private array $excludedIds;
    private array $megaEvolutions;
    private EntityManagerInterface $entityManager;
    private PokemonVariationRepository $variationRepository;
    private ?array $variations = null;

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
        $this->entityManager = $entityManager;
        $this->variationRepository = $variationRepository;
    }

    /**
     * Garante o carregamento sob demanda das variações (Lazy Loading) com fallback robusto
     */
    private function getVariationsList(): array
    {
        if ($this->variations === null) {
            $this->initializeDatabaseAndVariations();
            $this->variations = [];
            try {
                $dbVariations = $this->variationRepository->findAll();
                foreach ($dbVariations as $var) {
                    $this->variations[$var->getId()] = [
                        'base_id' => $var->getBaseId(),
                        'name' => $var->getName()
                    ];
                }
            } catch (\Exception $e) {
                // Fallback para a configuração padrão em caso de tabela inexistente ou erro de conexão
                foreach (PokemonConfig::DEFAULT_VARIATIONS as $id => $data) {
                    $this->variations[$id] = [
                        'base_id' => $data['base_id'],
                        'name' => $data['name']
                    ];
                }
            }
        }
        return $this->variations;
    }

    /**
     * Inicializa a tabela pokemon_variation a partir das configurações.
     * Insere as variações padrão iniciais caso a tabela esteja vazia, ou força a sincronização se $force for true.
     */
    public function initializeDatabaseAndVariations(bool $force = false): void
    {
        try {
            $connection = $this->entityManager->getConnection();
            
            if (!$force) {
                // Só inicializa se a tabela estiver completamente vazia
                $count = (int) $connection->fetchOne('SELECT COUNT(*) FROM pokemon_variation');
                if ($count > 0) {
                    return;
                }
            } else {
                // Limpa a tabela para forçar sincronização total
                $connection->executeStatement('DELETE FROM pokemon_variation');
            }

            foreach (PokemonConfig::DEFAULT_VARIATIONS as $id => $data) {
                $connection->insert('pokemon_variation', [
                    'id' => $id,
                    'base_id' => $data['base_id'],
                    'name' => $data['name']
                ]);
            }
        } catch (\Exception $e) {
            // Silencioso se der erro (ex: tabela ainda não criada)
        }
    }

    public function getMegaEvolutions(): array
    {
        return $this->megaEvolutions;
    }

    public function getVariations(): array
    {
        return $this->getVariationsList();
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
        $variations = $this->getVariationsList();
        if (isset($variations[$id])) {
            return $variations[$id]['base_id'];
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
