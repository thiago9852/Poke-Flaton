<?php

namespace App\Repository;

use App\Entity\PokemonAccess;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PokemonAccess>
 *
 * @method PokemonAccess|null find($id, $lockMode = null, $lockVersion = null)
 * @method PokemonAccess|null findOneBy(array $criteria, array $orderBy = null)
 * @method PokemonAccess[]    findAll()
 * @method PokemonAccess[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PokemonAccessRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PokemonAccess::class);
    }

    /**
     * @return PokemonAccess[] Returns an array of the most visited PokemonAccess objects
     */
    public function findTrending(int $limit): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.views', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
