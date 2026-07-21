<?php

namespace App\Repository;

use App\Entity\Moveset;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Moveset>
 */
class MovesetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Moveset::class);
    }

    /**
     * @return Moveset[]
     */
    public function findByPokemon(string $pokemonName): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.pokemonName = :name')
            ->setParameter('name', $pokemonName)
            ->orderBy('m.votes', 'DESC')
            ->addOrderBy('m.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Obter a contagem de movesets agrupados pelo nome do pokémon
     */
    public function getMovesetCountsGroupedByPokemon(): array
    {
        $results = $this->createQueryBuilder('m')
            ->select('m.pokemonName, COUNT(m.id) as cnt')
            ->groupBy('m.pokemonName')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($results as $row) {
            $counts[strtolower($row['pokemonName'])] = (int)$row['cnt'];
        }
        return $counts;
    }
}
