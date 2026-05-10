<?php

namespace App\Repository;

use App\Entity\PokemonLocation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PokemonLocation>
 */
class PokemonLocationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PokemonLocation::class);
    }

    /**
     * @return PokemonLocation[]
     */
    public function findByPokemon(string $pokemonName): array
    {
        return $this->createQueryBuilder('pl')
            ->andWhere('pl.pokemonName = :name')
            ->setParameter('name', strtolower($pokemonName))
            ->orderBy('pl.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
