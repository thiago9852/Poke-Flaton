<?php

namespace App\Repository;

use App\Entity\EvolutionRule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EvolutionRule>
 *
 * @method EvolutionRule|null find($id, $lockMode = null, $lockVersion = null)
 * @method EvolutionRule|null findOneBy(array $criteria, array $orderBy = null)
 * @method EvolutionRule[]    findAll()
 * @method EvolutionRule[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EvolutionRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EvolutionRule::class);
    }
}
