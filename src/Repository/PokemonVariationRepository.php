<?php

namespace App\Repository;

use App\Entity\PokemonVariation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PokemonVariation>
 *
 * @method PokemonVariation|null find($id, $lockMode = null, $lockVersion = null)
 * @method PokemonVariation|null findOneBy(array $criteria, array $orderBy = null)
 * @method PokemonVariation[]    findAll()
 * @method PokemonVariation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PokemonVariationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PokemonVariation::class);
    }
}
