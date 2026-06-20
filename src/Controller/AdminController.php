<?php

namespace App\Controller;

use App\Entity\Title;
use App\Entity\PokemonVariation;
use App\Entity\EvolutionRule;
use App\Entity\PokemonLocation;
use App\Enum\EvolutionStone;
use App\Repository\UserRepository;
use App\Repository\TitleRepository;
use App\Repository\CardTemplateRepository;
use App\Repository\PokemonVariationRepository;
use App\Service\TrainerProfileService;
use App\Service\PokeApiService;
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
    private CardTemplateRepository $cardTemplateRepository;
    private PokemonVariationRepository $variationRepository;
    private EntityManagerInterface $entityManager;
    private TrainerProfileService $trainerProfileService;
    private PokeApiService $pokeApiService;

    public function __construct(
        #[Autowire('%kernel.project_dir%')] string $projectDir,
        UserRepository $userRepository,
        TitleRepository $titleRepository,
        CardTemplateRepository $cardTemplateRepository,
        PokemonVariationRepository $variationRepository,
        EntityManagerInterface $entityManager,
        TrainerProfileService $trainerProfileService,
        PokeApiService $pokeApiService
    ) {
        $this->projectDir     = $projectDir;
        $this->userRepository = $userRepository;
        $this->titleRepository = $titleRepository;
        $this->cardTemplateRepository = $cardTemplateRepository;
        $this->variationRepository = $variationRepository;
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

        // Garante a tabela de títulos, templates e avatares
        $this->trainerProfileService->initializeDatabaseAndTitles();
        $this->trainerProfileService->initializeDatabaseAndCardTemplates();
        $this->trainerProfileService->initializeDatabaseAndAvatars();
 
        $config                = $this->loadConfig();
        $enabledMedals         = $config['enabled_medals'] ?? [];
        $enabledVivillonPats   = $config['enabled_vivillon_patterns'] ?? [];
        $medalDefs             = \App\Enum\Medal::getDefinitionsGrouped();
        $allVivillonPatterns   = \App\Enum\VivillonPattern::values();
        $baseUrl               = 'https://raw.githubusercontent.com/KovuTheHusky/pokemon-medals/main/';
        $totalUsers            = count($this->userRepository->findAll());
        $titles                = $this->titleRepository->findAll();
        $templates             = $this->cardTemplateRepository->findAll();
        $avatars               = $this->entityManager->getRepository(\App\Entity\Avatar::class)->findBy([], ['type' => 'ASC', 'filename' => 'ASC']);
 
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
            'templates'           => $templates,
            'avatars'             => $avatars,
        ]);
    }

    #[Route('/admin/pokemon', name: 'app_admin_pokemon', methods: ['GET'])]
    public function adminPokemon(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $activeTab = $request->query->get('tab', 'users');
        $pokemonSearch = trim($request->query->get('pokemon', ''));

        $gameEncounters = [];
        if (!empty($pokemonSearch)) {
            try {
                $gameEncounters = $this->pokeApiService->getPokemonEncounters(strtolower($pokemonSearch));
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erro ao buscar localizações oficiais: ' . $e->getMessage());
            }
        }

        $pendingLocations = $this->entityManager->getRepository(PokemonLocation::class)->findBy(['isApproved' => false], ['createdAt' => 'DESC']);
        $variations = $this->variationRepository->findBy([], ['id' => 'ASC']);
        $evolutionRules = $this->entityManager->getRepository(EvolutionRule::class)->findBy([], ['basePokemon' => 'ASC']);
        $pokemonList = $this->pokeApiService->getPokemonBasicList();

        $pokemonByName = [];
        foreach ($pokemonList as $pkm) {
            $pokemonByName[strtolower($pkm['name'])] = $pkm;
        }

        $stones = EvolutionStone::cases();

        return $this->render('admin/pokemon.html.twig', [
            'activeTab' => $activeTab,
            'pokemonSearch' => $pokemonSearch,
            'gameEncounters' => $gameEncounters,
            'pendingLocations' => $pendingLocations,
            'variations' => $variations,
            'evolutionRules' => $evolutionRules,
            'pokemonList' => $pokemonList,
            'pokemonByName' => $pokemonByName,
            'stones' => $stones,
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

    #[Route('/admin/templates/sync', name: 'app_admin_templates_sync', methods: ['POST'])]
    public function syncTemplates(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $result = $this->trainerProfileService->syncTemplatesFromApi();
        
        $this->addFlash('success', sprintf('Sincronização concluída! %d novos templates inseridos (Total indexado: %d).', $result['inserted'], $result['total']));
        
        return $this->redirectToRoute('app_admin', ['tab' => 'users', '_fragment' => 'card-templates-section']);
    }

    #[Route('/admin/templates/reset', name: 'app_admin_templates_reset', methods: ['POST'])]
    public function resetTemplates(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $result = $this->trainerProfileService->resetAndSyncTemplates();
        
        $this->addFlash('success', sprintf('Banco de templates resetado e sincronizado com sucesso! Total indexado: %d.', $result['total']));
        
        return $this->redirectToRoute('app_admin', ['tab' => 'users', '_fragment' => 'card-templates-section']);
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

        $this->entityManager->remove($template);
        $this->entityManager->flush();

        $this->addFlash('success', 'Plano de fundo excluído com sucesso!');
        return $this->redirectToRoute('app_admin', ['tab' => 'users', '_fragment' => 'card-templates-section']);
    }

    #[Route('/admin/template/{id}/update', name: 'app_admin_template_update', methods: ['POST'])]
    public function updateTemplateRules(int $id, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $template = $this->cardTemplateRepository->find($id);
        if (!$template) {
            $this->addFlash('error', 'Plano de fundo não encontrado.');
            return $this->redirectToRoute('app_admin');
        }

        $form = $this->createForm(\App\Form\CardTemplateRulesType::class, $template);
        $data = $request->request->all();
        if (!isset($data['isDefault'])) {
            $data['isDefault'] = false;
        }
        $form->submit($data);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($template->getRequirement() === '' || $template->getRequirement() === null) {
                $template->setRequirement('Bloqueado por padrão');
            }
            if ($template->getReqMedal() === '') $template->setReqMedal(null);
            if ($template->getReqTier() === '') $template->setReqTier(null);
            if ($template->getReqRankType() === '') $template->setReqRankType(null);

            $this->entityManager->persist($template);
            $this->entityManager->flush();
            $this->addFlash('success', 'Regras do plano de fundo atualizadas com sucesso!');
        } else {
            $this->addFlash('error', 'Erro ao atualizar as regras do plano de fundo.');
        }

        return $this->redirectToRoute('app_admin', ['tab' => 'users', '_fragment' => 'card-templates-section']);
    }

    #[Route('/admin/title/{id}/update', name: 'app_admin_title_update', methods: ['POST'])]
    public function updateTitleRules(int $id, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $title = $this->titleRepository->find($id);
        if (!$title) {
            $this->addFlash('error', 'Título não encontrado.');
            return $this->redirectToRoute('app_admin');
        }

        $form = $this->createForm(\App\Form\TitleRulesType::class, $title);
        $data = $request->request->all();
        if (!isset($data['isDefault'])) {
            $data['isDefault'] = false;
        }
        $form->submit($data);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($title->getRequirement() === '' || $title->getRequirement() === null) {
                $title->setRequirement('Bloqueado por padrão');
            }
            if ($title->getReqMedal() === '') $title->setReqMedal(null);
            if ($title->getReqTier() === '') $title->setReqTier(null);
            if ($title->getReqRankType() === '') $title->setReqRankType(null);

            $this->entityManager->persist($title);
            $this->entityManager->flush();
            $this->addFlash('success', 'Regras do título atualizadas com sucesso!');
        } else {
            $this->addFlash('error', 'Erro ao atualizar as regras do título.');
        }

        return $this->redirectToRoute('app_admin', ['tab' => 'users', '_fragment' => 'trainer-titles-section']);
    }

    #[Route('/admin/avatars/sync', name: 'app_admin_avatars_sync', methods: ['POST'])]
    public function syncAvatars(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $result = $this->trainerProfileService->syncAvatarsFromApi();
        
        $this->addFlash('success', sprintf('Sincronização concluída! %d novos avatares inseridos (Total indexado: %d).', $result['inserted'], $result['total']));
        
        return $this->redirectToRoute('app_admin', ['tab' => 'users', '_fragment' => 'avatars-section']);
    }

    #[Route('/admin/avatars/reset', name: 'app_admin_avatars_reset', methods: ['POST'])]
    public function resetAvatars(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $result = $this->trainerProfileService->resetAndSyncAvatars();
        
        $this->addFlash('success', sprintf('Banco de avatares resetado e sincronizado com sucesso! total indexado: %d.', $result['total']));
        
        return $this->redirectToRoute('app_admin', ['tab' => 'users', '_fragment' => 'avatars-section']);
    }

    #[Route('/admin/avatar/{id}/update', name: 'app_admin_avatar_update', methods: ['POST'])]
    public function updateAvatarRules(int $id, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $avatar = $this->entityManager->getRepository(\App\Entity\Avatar::class)->find($id);
        if (!$avatar) {
            $this->addFlash('error', 'Avatar não encontrado.');
            return $this->redirectToRoute('app_admin');
        }

        $form = $this->createForm(\App\Form\AvatarRulesType::class, $avatar);
        $data = $request->request->all();
        if (!isset($data['isDefault'])) {
            $data['isDefault'] = false;
        }
        $form->submit($data);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($avatar->getRequirement() === '') $avatar->setRequirement(null);
            if ($avatar->getReqMedal() === '') $avatar->setReqMedal(null);
            if ($avatar->getReqTier() === '') $avatar->setReqTier(null);
            if ($avatar->getReqRankType() === '') $avatar->setReqRankType(null);

            $this->entityManager->persist($avatar);
            $this->entityManager->flush();
            $this->addFlash('success', 'Regras do avatar atualizadas com sucesso!');
        } else {
            $this->addFlash('error', 'Erro ao atualizar as regras do avatar.');
        }

        return $this->redirectToRoute('app_admin', ['tab' => 'users', '_fragment' => 'avatars-section']);
    }

    // Variações de Pokémon

    #[Route('/admin/variation/add', name: 'app_admin_variation_add', methods: ['POST'])]
    public function addVariation(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $variation = new PokemonVariation();
        $form = $this->createForm(\App\Form\PokemonVariationType::class, $variation);
        
        $data = [
            'id' => $request->request->get('variation_id'),
            'baseId' => $request->request->get('base_id'),
            'name' => $request->request->get('name'),
        ];
        $form->submit($data);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->variationRepository->find($variation->getId())) {
                $this->addFlash('error', "Já existe uma variação com o ID #{$variation->getId()}.");
            } else {
                $variation->setName(strtolower($variation->getName()));
                $this->entityManager->persist($variation);
                $this->entityManager->flush();
                $this->addFlash('success', sprintf('Variação "%s" (ID: %d) adicionada com sucesso!', $variation->getName(), $variation->getId()));
            }
        } else {
            $this->addFlash('error', 'Preencha todos os campos obrigatórios corretamente (ID, Base ID e Nome).');
        }

        return $this->redirectToRoute('app_admin_pokemon', ['_fragment' => 'variations-section']);
    }

    #[Route('/admin/variation/{id}/delete', name: 'app_admin_variation_delete', methods: ['POST'])]
    public function deleteVariation(int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $variation = $this->variationRepository->find($id);
        if (!$variation) {
            $this->addFlash('error', 'Variação não encontrada.');
            return $this->redirectToRoute('app_admin_pokemon', ['_fragment' => 'variations-section']);
        }

        $name = $variation->getName();
        $this->entityManager->remove($variation);
        $this->entityManager->flush();

        $this->addFlash('success', "Variação \"$name\" removida com sucesso!");
        return $this->redirectToRoute('app_admin_pokemon', ['_fragment' => 'variations-section']);
    }

    #[Route('/admin/evolution-rule/add', name: 'app_admin_evolution_rule_add', methods: ['POST'])]
    public function addEvolutionRule(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $rule = new EvolutionRule();
        $form = $this->createForm(\App\Form\EvolutionRuleType::class, $rule);
        
        $data = [
            'basePokemon' => $request->request->get('base_pokemon'),
            'evolvedPokemon' => $request->request->get('evolved_pokemon'),
            'evolutionStone' => $request->request->get('evolution_stone'),
            'customStone' => $request->request->get('custom_stone'),
            'gender' => $request->request->get('gender'),
        ];
        $form->submit($data);

        if ($form->isSubmitted() && $form->isValid()) {
            $basePokemon = $this->getSpeciesName($rule->getBasePokemon());
            $evolvedPokemon = $this->getSpeciesName($rule->getEvolvedPokemon());
            
            $evolutionStone = $form->get('evolutionStone')->getData();
            $customStone = $form->get('customStone')->getData();
            $gender = $rule->getGender();

            // Determinar o nome da pedra
            $stoneName = $evolutionStone;
            if ($evolutionStone === 'custom') {
                $stoneName = $customStone ?: 'Pedra Especial';
            } else {
                $enumStone = EvolutionStone::tryFrom($evolutionStone);
                if ($enumStone) {
                    $stoneName = $enumStone->getLabel();
                }
            }

            $method = $stoneName;
            $dbGender = ($gender === 'male' || $gender === 'female') ? $gender : null;

            // Buscar se já existe uma regra
            $evolutionRuleRepository = $this->entityManager->getRepository(EvolutionRule::class);
            $existingRule = $evolutionRuleRepository->findOneBy([
                'basePokemon' => $basePokemon,
                'evolvedPokemon' => $evolvedPokemon
            ]);

            if ($existingRule) {
                $rule = $existingRule;
            } else {
                $rule->setBasePokemon($basePokemon);
                $rule->setEvolvedPokemon($evolvedPokemon);
            }

            $rule->setMethod($method);
            $rule->setGender($dbGender);

            $this->entityManager->persist($rule);
            $this->entityManager->flush();

            $this->addFlash('success', "Regra de evolução de \"$basePokemon\" para \"$evolvedPokemon\" associada com sucesso!");
        } else {
            $this->addFlash('error', 'Erro ao adicionar regra de evolução. Verifique se preencheu todos os campos obrigatórios.');
        }

        return $this->redirectToRoute('app_admin_pokemon', ['_fragment' => 'evolution-rules-section']);
    }

    #[Route('/admin/evolution-rule/{id}/delete', name: 'app_admin_evolution_rule_delete', methods: ['POST'])]
    public function deleteEvolutionRule(int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $evolutionRuleRepository = $this->entityManager->getRepository(EvolutionRule::class);
        $rule = $evolutionRuleRepository->find($id);

        if (!$rule) {
            $this->addFlash('error', 'Regra de evolução não encontrada.');
            return $this->redirectToRoute('app_admin_pokemon', ['_fragment' => 'evolution-rules-section']);
        }

        $base = $rule->getBasePokemon();
        $evolved = $rule->getEvolvedPokemon();

        $this->entityManager->remove($rule);
        $this->entityManager->flush();

        $this->addFlash('success', "Regra de evolução de \"$base\" para \"$evolved\" removida com sucesso!");
        return $this->redirectToRoute('app_admin_pokemon', ['_fragment' => 'evolution-rules-section']);
    }

    #[Route('/admin/import/{resource}', name: 'app_admin_import_resource', methods: ['POST'])]
    public function importResource(string $resource, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $file = $request->files->get('import_file');
        if (!$file) {
            $this->addFlash('error', 'Por favor, envie um arquivo JSON.');
            return $this->redirectAfterImport($resource);
        }

        $content = file_get_contents($file->getPathname());
        $data = json_decode($content, true);

        if (!is_array($data)) {
            $this->addFlash('error', 'Arquivo JSON inválido. Esperado um array de dados.');
            return $this->redirectAfterImport($resource);
        }

        try {
            $count = $this->processBulkImport($resource, $data);
            $this->addFlash('success', "Importação concluída! $count registros de $resource foram criados/atualizados.");
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erro durante a importação: ' . $e->getMessage());
        }

        return $this->redirectAfterImport($resource);
    }

    #[Route('/admin/api/import/{resource}', name: 'app_admin_api_import_resource', methods: ['POST'])]
    public function apiImportResource(string $resource, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return new JsonResponse(['success' => false, 'error' => 'Payload inválido. Esperado um array JSON.'], 400);
        }

        try {
            $count = $this->processBulkImport($resource, $data);
            return new JsonResponse([
                'success' => true,
                'message' => "Processamento concluído. $count registros de $resource foram criados ou atualizados."
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function redirectAfterImport(string $resource): Response
    {
        $fragment = 'moderation-section';
        if ($resource === 'evolutions') {
            $fragment = 'evolution-rules-section';
        } elseif ($resource === 'variations') {
            $fragment = 'variations-section';
        }
        return $this->redirectToRoute('app_admin_pokemon', ['_fragment' => $fragment]);
    }

    private function processBulkImport(string $resource, array $data): int
    {
        $count = 0;

        if ($resource === 'evolutions') {
            foreach ($data as $item) {
                $baseInput = strtolower(trim($item['base'] ?? ''));
                $evolvedInput = strtolower(trim($item['evolved'] ?? ''));
                $stoneInput = trim($item['stone'] ?? '');
                $methodInput = trim($item['method'] ?? '');
                $gender = trim($item['gender'] ?? 'both');

                if (empty($baseInput) || empty($evolvedInput)) {
                    continue;
                }

                $basePokemon = $this->getSpeciesName($baseInput);
                $evolvedPokemon = $this->getSpeciesName($evolvedInput);

                $dbGender = ($gender === 'male' || $gender === 'female') ? $gender : null;

                if (!empty($methodInput)) {
                    $method = $methodInput;
                    if (preg_match('/\s*-\s*Apenas Macho\s*♂?/u', $method) || str_contains($method, '♂')) {
                        $method = preg_replace('/\s*-\s*Apenas Macho\s*♂?/u', '', $method);
                        $method = str_replace('♂', '', $method);
                        $dbGender = 'male';
                    } elseif (preg_match('/\s*-\s*Apenas Fêmea\s*♀?/u', $method) || str_contains($method, '♀')) {
                        $method = preg_replace('/\s*-\s*Apenas Fêmea\s*♀?/u', '', $method);
                        $method = str_replace('♀', '', $method);
                        $dbGender = 'female';
                    }
                    $method = trim($method);
                } else {
                    $stoneName = $stoneInput ?: 'Nível/Stone';
                    $enumStone = EvolutionStone::tryFrom($stoneInput);
                    if ($enumStone) {
                        $stoneName = $enumStone->getLabel();
                    }
                    $method = $stoneName;
                }

                $rule = $this->entityManager->getRepository(EvolutionRule::class)->findOneBy([
                    'basePokemon' => $basePokemon,
                    'evolvedPokemon' => $evolvedPokemon
                ]);

                if (!$rule) {
                    $rule = new EvolutionRule();
                    $rule->setBasePokemon($basePokemon);
                    $rule->setEvolvedPokemon($evolvedPokemon);
                }

                $rule->setMethod($method);
                $rule->setGender($dbGender);
                $this->entityManager->persist($rule);
                $count++;
            }
        } elseif ($resource === 'variations') {
            foreach ($data as $item) {
                $id = isset($item['id']) ? (int) $item['id'] : null;
                $baseId = isset($item['baseId']) ? (int) $item['baseId'] : (isset($item['base_id']) ? (int) $item['base_id'] : null);
                $name = strtolower(trim($item['name'] ?? ''));

                if (!$id || !$baseId || empty($name)) {
                    continue;
                }

                $variation = $this->variationRepository->find($id);
                if (!$variation) {
                    $variation = new PokemonVariation();
                    $variation->setId($id);
                }

                $variation->setBaseId($baseId);
                $variation->setName($name);

                $this->entityManager->persist($variation);
                $count++;
            }
        } elseif ($resource === 'locations') {
            foreach ($data as $item) {
                $pkmNameInput = strtolower(trim($item['pokemon'] ?? ($item['pokemon_name'] ?? '')));
                $locNameInput = trim($item['location'] ?? ($item['location_name'] ?? ''));
                $isApproved = isset($item['isApproved']) ? (bool) $item['isApproved'] : (isset($item['is_approved']) ? (bool) $item['is_approved'] : true);

                if (empty($pkmNameInput) || empty($locNameInput)) {
                    continue;
                }

                $pokemonName = $this->getSpeciesName($pkmNameInput);

                $loc = $this->entityManager->getRepository(PokemonLocation::class)->findOneBy([
                    'pokemonName' => $pokemonName,
                    'locationName' => $locNameInput
                ]);

                if (!$loc) {
                    $loc = new PokemonLocation();
                    $loc->setPokemonName($pokemonName);
                    $loc->setLocationName($locNameInput);
                }

                $loc->setIsApproved($isApproved);
                $this->entityManager->persist($loc);
                $count++;
            }
        } else {
            throw new \InvalidArgumentException("Tipo de recurso '$resource' não suportado para importação em lote.");
        }

        $this->entityManager->flush();
        return $count;
    }

    private function getSpeciesName(string $name): string
    {
        $name = strtolower(trim($name));
        
        // Remove sufixos comuns que indicam região/forma mas NÃO fazem parte do nome da espécie
        $suffixes = [
            '-alola', '-galar', '-hisui', '-paldea',
            '-amped', '-low-key',
            '-plant', '-sandy', '-trash',
            '-red-striped', '-blue-striped', '-white-striped',
            '-disguised', '-busted'
        ];
        
        foreach ($suffixes as $suffix) {
            if (str_ends_with($name, $suffix)) {
                return substr($name, 0, -strlen($suffix));
            }
        }
        
        // Sobreposições de caso especial se houver
        $map = [
            'burmy-plant' => 'burmy',
            'burmy-sandy' => 'burmy',
            'burmy-trash' => 'burmy',
            'wormadam-plant' => 'wormadam',
            'wormadam-sandy' => 'wormadam',
            'wormadam-trash' => 'wormadam',
        ];
        
        return $map[$name] ?? $name;
    }
}
