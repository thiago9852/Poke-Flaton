<?php

namespace App\Controller;

use App\Service\PokeApiService;
use App\Entity\TierList;
use App\Enum\TypeEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class TierListController extends AbstractController
{
    private PokeApiService $pokeApiService;
    private EntityManagerInterface $entityManager;

    public function __construct(PokeApiService $pokeApiService, EntityManagerInterface $entityManager)
    {
        $this->pokeApiService = $pokeApiService;
        $this->entityManager = $entityManager;
    }

    /**
     * Lógica "Self-Healing": garante que a tabela existirá no banco de dados.
     */
    private function ensureTableExists(): void
    {
        $conn = $this->entityManager->getConnection();
        $schemaManager = $conn->createSchemaManager();
        if (!$schemaManager->tablesExist(['tier_list'])) {
            $sql = "CREATE TABLE tier_list (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(100) NOT NULL,
                user_id INT NULL,
                state JSON NOT NULL,
                tags JSON NOT NULL,
                created_at DATETIME NOT NULL,
                FOREIGN KEY (user_id) REFERENCES `user`(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
            $conn->executeStatement($sql);
        }
    }

    #[Route('/tier-list', name: 'app_tier_list', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->ensureTableExists();
        $tagFilter = $request->query->get('tag', '');

        // Busca todas as Tier Lists
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('t')
           ->from(TierList::class, 't')
           ->orderBy('t.createdAt', 'DESC');

        if (!empty($tagFilter)) {
            $qb->andWhere('t.tags LIKE :tag')
               ->setParameter('tag', '%"' . $tagFilter . '"%');
        }

        $tierLists = $qb->getQuery()->getResult();

        return $this->render('tier_list/index.html.twig', [
            'tierLists' => $tierLists,
            'selectedTag' => $tagFilter,
        ]);
    }

    #[Route('/tier-list/criar', name: 'app_tier_list_create', methods: ['GET'])]
    public function create(Request $request): Response
    {
        $this->ensureTableExists();
        $cloneId = $request->query->get('clone');
        $clonedTier = null;

        if ($cloneId) {
            $clonedTier = $this->entityManager->getRepository(TierList::class)->find($cloneId);
        }

        $pokemonList = $this->pokeApiService->getPokemonBasicList();
        
        // Ordena por ID
        usort($pokemonList, function ($a, $b) {
            $idA = $a['dex_id'] ?? $a['id'];
            $idB = $b['dex_id'] ?? $b['id'];
            if ($idA === $idB) {
                return $a['id'] <=> $b['id'];
            }
            return $idA <=> $idB;
        });

        // Mapa de tipos
        $typesMap = [];
        foreach (TypeEnum::getCasesForModule('type') as $typeEnum) {
            $typeStr = $typeEnum->value;
            $pokemonOfType = $this->pokeApiService->getPokemonBasicListByType($typeStr);
            foreach ($pokemonOfType as $p) {
                $typesMap[$p['id']][] = $typeStr;
            }
        }

        return $this->render('tier_list/create.html.twig', [
            'pokemonList' => $pokemonList,
            'typesMap' => $typesMap,
            'allTypes' => TypeEnum::getCasesForModule('type'),
            'allowedGenerations' => $this->pokeApiService->getAllowedGenerations(),
            'clonedTier' => $clonedTier,
        ]);
    }

    #[Route('/tier-list/salvar', name: 'app_tier_list_save', methods: ['POST'])]
    public function save(Request $request): JsonResponse
    {
        $this->ensureTableExists();
        
        $data = json_decode($request->getContent(), true);
        if (!$data || empty($data['title']) || empty($data['state'])) {
            return new JsonResponse(['error' => 'Dados incompletos fornecidos.'], 400);
        }

        try {
            $tierList = new TierList();
            $tierList->setTitle(trim($data['title']))
                     ->setState($data['state'])
                     ->setTags($data['tags'] ?? [])
                     ->setUser($this->getUser());

            $this->entityManager->persist($tierList);
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'id' => $tierList->getId()
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Falha ao salvar a Tier List: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/tier-list/{id}', name: 'app_tier_list_view', methods: ['GET'])]
    public function view(int $id): Response
    {
        $this->ensureTableExists();

        $tierList = $this->entityManager->getRepository(TierList::class)->find($id);
        if (!$tierList) {
            throw $this->createNotFoundException('Tier List não encontrada.');
        }

        // Mapeia os detalhes de Pokémon por ID para renderizar a imagem/nome na visualização estática
        $pokemonList = $this->pokeApiService->getPokemonBasicList();
        $pokemonMap = [];
        foreach ($pokemonList as $p) {
            $pokemonMap[$p['id']] = $p;
        }

        return $this->render('tier_list/view.html.twig', [
            'tierList' => $tierList,
            'pokemonMap' => $pokemonMap,
        ]);
    }
}
