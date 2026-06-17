<?php

namespace App\Repository;

use App\Entity\PokemonGameScore;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PokemonGameScore>
 */
class PokemonGameScoreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PokemonGameScore::class);
    }

    /**
     * Finds the Top 5 best unique users/scores for the month.
     * Groups by user or anonymous token to show only the best score of each trainer.
     */
    public function findMonthlyTop5(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $qb = $this->createQueryBuilder('s')
            ->select('s.username, MIN(s.attempts) as min_attempts, MAX(s.createdAt) as last_completed, u.avatar')
            ->leftJoin('s.user', 'u')
            ->where('s.won = :won')
            ->andWhere('s.createdAt BETWEEN :start AND :end')
            ->setParameter('won', true)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->groupBy('s.user', 's.userToken', 's.username')
            ->orderBy('min_attempts', 'ASC')
            ->addOrderBy('last_completed', 'ASC')
            ->setMaxResults(5);

        $results = $qb->getQuery()->getResult();
        
        $formatted = [];
        foreach ($results as $row) {
            $formatted[] = [
                'username' => $row['username'],
                'attempts' => (int) $row['min_attempts'],
                'createdAt' => $row['last_completed'] instanceof \DateTimeInterface 
                    ? $row['last_completed'] 
                    : new \DateTimeImmutable((string)$row['last_completed']),
                'avatar' => $row['avatar']
            ];
        }

        return $formatted;
    }
}
