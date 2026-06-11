<?php

namespace App\Controller;

use App\Entity\Title;
use App\Repository\UserRepository;
use App\Repository\TitleRepository;
use App\Service\TrainerProfileService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminController extends AbstractController
{
    private string $projectDir;
    private UserRepository $userRepository;
    private TitleRepository $titleRepository;
    private EntityManagerInterface $entityManager;
    private TrainerProfileService $trainerProfileService;

    public function __construct(
        #[Autowire('%kernel.project_dir%')] string $projectDir,
        UserRepository $userRepository,
        TitleRepository $titleRepository,
        EntityManagerInterface $entityManager,
        TrainerProfileService $trainerProfileService
    ) {
        $this->projectDir     = $projectDir;
        $this->userRepository = $userRepository;
        $this->titleRepository = $titleRepository;
        $this->entityManager = $entityManager;
        $this->trainerProfileService = $trainerProfileService;
    }



    private function loadConfig(): array
    {
        $path = $this->projectDir . '/scratch/medals_config.json';
        if (!file_exists($path)) {
            return ['enabled_medals' => [], 'enabled_vivillon_patterns' => []];
        }
        return json_decode(file_get_contents($path), true) ?? ['enabled_medals' => [], 'enabled_vivillon_patterns' => []];
    }

    private function saveConfig(array $config): void
    {
        $path = $this->projectDir . '/scratch/medals_config.json';
        file_put_contents($path, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    #[Route('/admin', name: 'app_admin', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Garante a tabela de títulos
        $this->trainerProfileService->initializeDatabaseAndTitles();

        $config                = $this->loadConfig();
        $enabledMedals         = $config['enabled_medals'] ?? [];
        $enabledVivillonPats   = $config['enabled_vivillon_patterns'] ?? [];
        $medalDefs             = \App\Enum\Medal::getDefinitionsGrouped();
        $allVivillonPatterns   = \App\Enum\VivillonPattern::values();
        $baseUrl               = 'https://raw.githubusercontent.com/KovuTheHusky/pokemon-medals/main/';
        $totalUsers            = count($this->userRepository->findAll());
        $titles                = $this->titleRepository->findAll();

        $newTitle = new Title();
        $form = $this->createForm(\App\Form\TitleType::class, $newTitle, [
            'action' => $this->generateUrl('app_admin_title_add')
        ]);

        return $this->render('admin/index.html.twig', [
            'medalDefs'           => $medalDefs,
            'enabledMedals'       => $enabledMedals,
            'enabledVivillonPats' => $enabledVivillonPats,
            'allVivillonPatterns' => $allVivillonPatterns,
            'badgeBaseUrl'        => $baseUrl,
            'totalUsers'          => $totalUsers,
            'titles'              => $titles,
            'titleForm'           => $form->createView(),
        ]);
    }

    #[Route('/admin/medal/toggle', name: 'app_admin_medal_toggle', methods: ['POST'])]
    public function toggleMedal(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $medal   = $request->request->get('medal', '');
        $enabled = $request->request->get('enabled', 'false') === 'true';
        $type    = $request->request->get('type', 'medal'); // 'medal' ou 'vivillon'

        if (empty($medal)) {
            return new JsonResponse(['error' => 'Invalid medal'], 400);
        }

        $config = $this->loadConfig();

        if ($type === 'vivillon') {
            $patterns = $config['enabled_vivillon_patterns'] ?? [];
            if ($enabled && !in_array($medal, $patterns)) {
                $patterns[] = $medal;
            } elseif (!$enabled) {
                $patterns = array_values(array_filter($patterns, fn($p) => $p !== $medal));
            }
            $config['enabled_vivillon_patterns'] = $patterns;
        } else {
            $medals = $config['enabled_medals'] ?? [];
            if ($enabled && !in_array($medal, $medals)) {
                $medals[] = $medal;
            } elseif (!$enabled) {
                $medals = array_values(array_filter($medals, fn($m) => $m !== $medal));
            }
            $config['enabled_medals'] = $medals;
        }

        $this->saveConfig($config);

        return new JsonResponse(['success' => true, 'medal' => $medal, 'enabled' => $enabled, 'type' => $type]);
    }

    #[Route('/admin/medals/bulk', name: 'app_admin_medals_bulk', methods: ['POST'])]
    public function bulkUpdate(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $action = $request->request->get('action', ''); // 'ativar/desativar todos'
        $config = $this->loadConfig();

        if ($action === 'enable_all') {
            $config['enabled_medals'] = array_column(\App\Enum\Medal::cases(), 'value');
            $config['enabled_vivillon_patterns'] = \App\Enum\VivillonPattern::values();
        } elseif ($action === 'disable_all') {
            $config['enabled_medals'] = [];
            $config['enabled_vivillon_patterns'] = [];
        }

        $this->saveConfig($config);
        return new JsonResponse(['success' => true, 'action' => $action]);
    }

    #[Route('/admin/title/add', name: 'app_admin_title_add', methods: ['POST'])]
    public function addTitle(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $title = new Title();
        $form = $this->createForm(\App\Form\TitleType::class, $title);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($title->isDefault()) {
                $defaults = $this->titleRepository->findBy(['isDefault' => true]);
                foreach ($defaults as $d) {
                    $d->setIsDefault(false);
                    $this->entityManager->persist($d);
                }
            }

            $this->entityManager->persist($title);
            $this->entityManager->flush();

            $this->addFlash('success', 'Título criado com sucesso!');
        } else {
            $this->addFlash('error', 'Erro ao criar título. Verifique os dados inseridos.');
        }

        return $this->redirectToRoute('app_admin');
    }

    #[Route('/admin/title/{id}/delete', name: 'app_admin_title_delete', methods: ['POST'])]
    public function deleteTitle(int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $title = $this->titleRepository->find($id);
        if (!$title) {
            $this->addFlash('error', 'Título não encontrado.');
            return $this->redirectToRoute('app_admin');
        }

        if ($title->isDefault()) {
            $this->addFlash('error', 'Você não pode excluir o título padrão.');
            return $this->redirectToRoute('app_admin');
        }

        $this->entityManager->remove($title);
        $this->entityManager->flush();

        $this->addFlash('success', 'Título excluído com sucesso!');
        return $this->redirectToRoute('app_admin');
    }
}
