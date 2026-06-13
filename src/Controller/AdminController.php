<?php

namespace App\Controller;

use App\Entity\Title;
use App\Entity\CardTemplate;
use App\Form\CardTemplateType;
use App\Repository\UserRepository;
use App\Repository\TitleRepository;
use App\Repository\CardTemplateRepository;
use App\Service\TrainerProfileService;
use App\Service\PokeApiService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class AdminController extends AbstractController
{
    private string $projectDir;
    private UserRepository $userRepository;
    private TitleRepository $titleRepository;
    private CardTemplateRepository $cardTemplateRepository;
    private EntityManagerInterface $entityManager;
    private TrainerProfileService $trainerProfileService;
    private PokeApiService $pokeApiService;

    public function __construct(
        #[Autowire('%kernel.project_dir%')] string $projectDir,
        UserRepository $userRepository,
        TitleRepository $titleRepository,
        CardTemplateRepository $cardTemplateRepository,
        EntityManagerInterface $entityManager,
        TrainerProfileService $trainerProfileService,
        PokeApiService $pokeApiService
    ) {
        $this->projectDir     = $projectDir;
        $this->userRepository = $userRepository;
        $this->titleRepository = $titleRepository;
        $this->cardTemplateRepository = $cardTemplateRepository;
        $this->entityManager = $entityManager;
        $this->trainerProfileService = $trainerProfileService;
        $this->pokeApiService = $pokeApiService;
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
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Garante a tabela de títulos e templates
        $this->trainerProfileService->initializeDatabaseAndTitles();
        $this->trainerProfileService->initializeDatabaseAndCardTemplates();
 
        $config                = $this->loadConfig();
        $enabledMedals         = $config['enabled_medals'] ?? [];
        $enabledVivillonPats   = $config['enabled_vivillon_patterns'] ?? [];
        $medalDefs             = \App\Enum\Medal::getDefinitionsGrouped();
        $allVivillonPatterns   = \App\Enum\VivillonPattern::values();
        $baseUrl               = 'https://raw.githubusercontent.com/KovuTheHusky/pokemon-medals/main/';
        $totalUsers            = count($this->userRepository->findAll());
        $titles                = $this->titleRepository->findAll();
        $templates             = $this->cardTemplateRepository->findAll();
 
        $newTitle = new Title();
        $form = $this->createForm(\App\Form\TitleType::class, $newTitle, [
            'action' => $this->generateUrl('app_admin_title_add')
        ]);

        $newTemplate = new CardTemplate();
        $templateForm = $this->createForm(\App\Form\CardTemplateType::class, $newTemplate, [
            'action' => $this->generateUrl('app_admin_card_template_add')
        ]);
 
        $pendingLocations = $this->entityManager->getRepository(\App\Entity\PokemonLocation::class)->findBy(
            ['isApproved' => false],
            ['createdAt' => 'DESC']
        );
 
        $activeTab = $request->query->get('tab', 'users');
        $pokemonSearch = trim($request->query->get('pokemon', ''));
        $gameEncounters = [];
 
        if ($activeTab === 'games' && !empty($pokemonSearch)) {
            try {
                $gameEncounters = $this->pokeApiService->getPokemonEncounters($pokemonSearch);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erro ao buscar encontros oficiais para "' . $pokemonSearch . '".');
            }
        }
 
        $pokemonList = [];
        try {
            $pokemonList = $this->pokeApiService->getPokemonBasicList();
        } catch (\Exception $e) {
            // ignore
        }
 
        return $this->render('admin/index.html.twig', [
            'medalDefs'           => $medalDefs,
            'enabledMedals'       => $enabledMedals,
            'enabledVivillonPats' => $enabledVivillonPats,
            'allVivillonPatterns' => $allVivillonPatterns,
            'badgeBaseUrl'        => $baseUrl,
            'totalUsers'          => $totalUsers,
            'titles'              => $titles,
            'titleForm'           => $form->createView(),
            'templates'           => $templates,
            'templateForm'        => $templateForm->createView(),
            'pendingLocations'    => $pendingLocations,
            'activeTab'           => $activeTab,
            'pokemonSearch'       => $pokemonSearch,
            'gameEncounters'      => $gameEncounters,
            'pokemonList'         => $pokemonList,
        ]);
    }

    #[Route('/admin/medal/toggle', name: 'app_admin_medal_toggle', methods: ['POST'])]
    public function toggleMedal(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $medal   = $request->request->get('medal', '');
        $enabled = $request->request->get('enabled', 'false') === 'true';
        $type    = $request->request->get('type', 'medal'); // 'medalha' ou 'vivillon'

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

    #[Route('/admin/card-template/new', name: 'app_admin_card_template_add', methods: ['POST'])]
    public function addCardTemplate(Request $request, SluggerInterface $slugger): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $template = new CardTemplate();
        $form = $this->createForm(CardTemplateType::class, $template);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                $uploadDir = $this->projectDir . '/public/uploads/templates';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                try {
                    $imageFile->move($uploadDir, $newFilename);
                    $template->setImage($newFilename);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Erro ao salvar o arquivo de imagem.');
                    return $this->redirectToRoute('app_admin');
                }
            } else {
                $this->addFlash('error', 'É necessário enviar um arquivo PNG para o plano de fundo.');
                return $this->redirectToRoute('app_admin');
            }

            if ($template->isDefault()) {
                $defaults = $this->cardTemplateRepository->findBy(['isDefault' => true]);
                foreach ($defaults as $d) {
                    $d->setIsDefault(false);
                    $this->entityManager->persist($d);
                }
            }

            $this->entityManager->persist($template);
            $this->entityManager->flush();

            $this->addFlash('success', 'Plano de fundo criado com sucesso!');
        } else {
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
            if ($form->getErrors(true)->count() === 0) {
                $this->addFlash('error', 'Erro ao criar plano de fundo. Verifique os dados inseridos.');
            }
        }

        return $this->redirectToRoute('app_admin');
    }

    #[Route('/admin/card-template/{id}/delete', name: 'app_admin_card_template_delete', methods: ['POST'])]
    public function deleteCardTemplate(int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $template = $this->cardTemplateRepository->find($id);
        if (!$template) {
            $this->addFlash('error', 'Plano de fundo não encontrado.');
            return $this->redirectToRoute('app_admin');
        }

        if ($template->isDefault()) {
            $this->addFlash('error', 'Você não pode excluir o plano de fundo padrão.');
            return $this->redirectToRoute('app_admin');
        }

        $filePath = $this->projectDir . '/public/uploads/templates/' . $template->getImage();
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $this->entityManager->remove($template);
        $this->entityManager->flush();

        $this->addFlash('success', 'Plano de fundo excluído com sucesso!');
        return $this->redirectToRoute('app_admin');
    }
}
