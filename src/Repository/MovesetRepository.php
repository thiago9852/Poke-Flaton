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
}
