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

    /**
     * Retrieves the daily success rate of games played in the last N days.
     * Calculates the win percentage based on the count of won games vs total games played.
     */
    public function findDailySuccessRates(int $days = 10): array
    {
        $qb = $this->createQueryBuilder('s')
            ->select('s.gameDate, s.won, COUNT(s.id) as cnt')
            ->groupBy('s.gameDate', 's.won')
            ->orderBy('s.gameDate', 'DESC');
            
        $results = $qb->getQuery()->getResult();
        
        $dailyData = [];
        foreach ($results as $row) {
            $date = $row['gameDate'] instanceof \DateTimeInterface 
                ? $row['gameDate']->format('Y-m-d') 
                : (string) $row['gameDate'];
            $won = (bool) $row['won'];
            $count = (int) $row['cnt'];
            
            if (!isset($dailyData[$date])) {
                $dailyData[$date] = [
                    'date' => $date,
                    'total' => 0,
                    'won' => 0
                ];
            }
            
            $dailyData[$date]['total'] += $count;
            if ($won) {
                $dailyData[$date]['won'] += $count;
            }
        }
        
        $formatted = [];
        foreach ($dailyData as $date => $data) {
            $total = $data['total'];
            $won = $data['won'];
            $rate = $total > 0 ? round(($won / $total) * 100, 1) : 0;
            
            $formatted[] = [
                'date' => $date,
                'total' => $total,
                'won' => $won,
                'rate' => $rate
            ];
        }
        
        usort($formatted, fn($a, $b) => strcmp($b['date'], $a['date']));
        
        return array_slice($formatted, 0, $days);
    }
}

