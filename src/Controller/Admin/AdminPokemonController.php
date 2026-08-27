<?php

namespace App\Controller\Admin;

use App\Entity\Avatar;
use App\Entity\CardTemplate;
use App\Entity\EvolutionRule;
use App\Entity\Moveset;
use App\Entity\PokemonLocation;
use App\Entity\PokemonVariation;
use App\Entity\User;
use App\Enum\EvolutionStone;
use App\Form\EvolutionRuleType;
use App\Form\PokemonVariationType;
use App\Repository\PokemonVariationRepository;
use App\Service\PokeApiService;
use App\Service\TrainerProfileService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminPokemonController extends AbstractController
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
        private readonly PokemonVariationRepository $variationRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly TrainerProfileService $trainerProfileService,
        private readonly PokeApiService $pokeApiService,
    ) {
    }

    #[Route('/admin/pokemon', name: 'app_admin_pokemon', methods: ['GET'])]
    public function adminPokemon(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Garante as colunas no banco
        $this->trainerProfileService->initializeMovesetColumns();

        $activeTab = $request->query->get('tab', 'overview');
        $pokemonSearch = trim($request->query->get('pokemon', ''));

        $gameEncounters = [];
        if (!empty($pokemonSearch)) {
            try {
                $gameEncounters = $this->pokeApiService->getPokemonEncounters(strtolower($pokemonSearch));
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erro ao buscar localizações oficiais: '.$e->getMessage());
            }
        }

        $pendingLocations = $this->entityManager->getRepository(PokemonLocation::class)->findBy(['isApproved' => false], ['createdAt' => 'DESC']);
        $pendingMovesets = $this->entityManager->getRepository(Moveset::class)->findBy(['suggestedDefault' => true], ['createdAt' => 'DESC']);
        $variations = $this->variationRepository->findBy([], ['id' => 'ASC']);
        $evolutionRules = $this->entityManager->getRepository(EvolutionRule::class)->findBy([], ['basePokemon' => 'ASC']);
        $pokemonList = $this->pokeApiService->getPokemonBasicList();

        $pokemonByName = [];
        foreach ($pokemonList as $pkm) {
            $pokemonByName[strtolower($pkm['name'])] = $pkm;
        }

        $stones = EvolutionStone::cases();

        // Carrega default base moves
        $defaultBaseMovesPath = $this->projectDir.'/scratch/default_base_moves.json';
        $defaultBaseMoves = [];
        if (file_exists($defaultBaseMovesPath)) {
            $defaultBaseMoves = json_decode(file_get_contents($defaultBaseMovesPath), true) ?: [];
        }

        // Estatísticas para o Dashboard
        $approvedLocationsCount = $this->entityManager->getRepository(PokemonLocation::class)->count(['isApproved' => true]);
        $totalMovesetsCount = $this->entityManager->getRepository(Moveset::class)->count([]);
        $cardTemplates = $this->entityManager->getRepository(CardTemplate::class)->findBy([], ['id' => 'ASC']);
        $avatarsCount = $this->entityManager->getRepository(Avatar::class)->count([]);
        $usersCount = $this->entityManager->getRepository(User::class)->count([]);

        $stats = [
            'pendingLocations' => count($pendingLocations),
            'approvedLocations' => $approvedLocationsCount,
            'pendingMovesets' => count($pendingMovesets),
            'totalMovesets' => $totalMovesetsCount,
            'variations' => count($variations),
            'evolutionRules' => count($evolutionRules),
            'defaultBaseMoves' => count($defaultBaseMoves),
            'cardTemplates' => count($cardTemplates),
            'avatars' => $avatarsCount,
            'users' => $usersCount,
        ];

        return $this->render('admin/pokemon.html.twig', [
            'activeTab' => $activeTab,
            'pokemonSearch' => $pokemonSearch,
            'gameEncounters' => $gameEncounters,
            'pendingLocations' => $pendingLocations,
            'pendingMovesets' => $pendingMovesets,
            'variations' => $variations,
            'evolutionRules' => $evolutionRules,
            'pokemonList' => $pokemonList,
            'pokemonByName' => $pokemonByName,
            'stones' => $stones,
            'defaultBaseMoves' => $defaultBaseMoves,
            'cardTemplates' => $cardTemplates,
            'stats' => $stats,
        ]);
    }

    #[Route('/admin/default-base-moves/save', name: 'app_admin_default_base_moves_save', methods: ['POST'])]
    public function saveDefaultBaseMoves(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $pokemonNameInput = strtolower(trim($request->request->get('pokemon', '')));
        $movesInput = $request->request->get('moves', '');
        $overwrite = $request->request->getBoolean('overwrite', false);

        if (empty($pokemonNameInput)) {
            $this->addFlash('error', 'Por favor, selecione um Pokémon.');

            return $this->redirectToRoute('app_admin_pokemon', ['_fragment' => 'default-base-moves-section']);
        }

        // Processa golpes
        $newMoves = [];
        if (!empty($movesInput)) {
            // Divide por vírgulas ou quebras de linha e limpa cada golpe
            $rawMoves = preg_split('/[,\n]+/', $movesInput);
            foreach ($rawMoves as $rawMove) {
                $moveNormalized = preg_replace('/-+/', '-', str_replace(' ', '-', strtolower(trim($rawMove))));
                if (!empty($moveNormalized)) {
                    $newMoves[] = $moveNormalized;
                }
            }
        }

        $defaultBaseMovesPath = $this->projectDir.'/scratch/default_base_moves.json';
        $defaultBaseMoves = [];
        if (file_exists($defaultBaseMovesPath)) {
            $defaultBaseMoves = json_decode(file_get_contents($defaultBaseMovesPath), true) ?: [];
        }

        if ($overwrite || !isset($defaultBaseMoves[$pokemonNameInput])) {
            $defaultBaseMoves[$pokemonNameInput] = $newMoves;
        } else {
            // Mescla golpes sem duplicar
            $defaultBaseMoves[$pokemonNameInput] = array_values(array_unique(array_merge($defaultBaseMoves[$pokemonNameInput], $newMoves)));
        }

        // Remove chaves vazias se não houver golpes configurados
        if (empty($defaultBaseMoves[$pokemonNameInput])) {
            unset($defaultBaseMoves[$pokemonNameInput]);
        }

        // Ordena por nome de pokemon
        ksort($defaultBaseMoves);

        file_put_contents(
            $defaultBaseMovesPath,
            json_encode($defaultBaseMoves, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        $this->addFlash('success', sprintf('Golpes base do Pokémon %s atualizados com sucesso!', ucfirst($pokemonNameInput)));

        return $this->redirectToRoute('app_admin_pokemon', ['_fragment' => 'default-base-moves-section']);
    }

    #[Route('/admin/default-base-moves/delete', name: 'app_admin_default_base_moves_delete', methods: ['POST'])]
    public function deleteDefaultBaseMoves(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $pokemonNameInput = strtolower(trim($request->request->get('pokemon', '')));
        $moveToDelete = strtolower(trim($request->request->get('move', '')));

        if (empty($pokemonNameInput)) {
            $this->addFlash('error', 'Pokémon não especificado.');

            return $this->redirectToRoute('app_admin_pokemon', ['_fragment' => 'default-base-moves-section']);
        }

        $defaultBaseMovesPath = $this->projectDir.'/scratch/default_base_moves.json';
        if (file_exists($defaultBaseMovesPath)) {
            $defaultBaseMoves = json_decode(file_get_contents($defaultBaseMovesPath), true) ?: [];

            if (isset($defaultBaseMoves[$pokemonNameInput])) {
                if (!empty($moveToDelete)) {
                    // Remove apenas o golpe especificado
                    $defaultBaseMoves[$pokemonNameInput] = array_values(array_filter(
                        $defaultBaseMoves[$pokemonNameInput],
                        fn ($m) => strtolower(trim($m)) !== $moveToDelete
                    ));

                    // Se a lista de golpes ficou vazia, remove o pokemon
                    if (empty($defaultBaseMoves[$pokemonNameInput])) {
                        unset($defaultBaseMoves[$pokemonNameInput]);
                    }
                    $msg = sprintf('Golpe "%s" removido de %s.', $moveToDelete, ucfirst($pokemonNameInput));
                } else {
                    // Remove o pokemon inteiro
                    unset($defaultBaseMoves[$pokemonNameInput]);
                    $msg = sprintf('Todos os golpes base de %s foram removidos.', ucfirst($pokemonNameInput));
                }

                file_put_contents(
                    $defaultBaseMovesPath,
                    json_encode($defaultBaseMoves, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                );

                $this->addFlash('success', $msg);
            }
        }

        return $this->redirectToRoute('app_admin_pokemon', ['_fragment' => 'default-base-moves-section']);
    }

    // Variações de Pokémon

    #[Route('/admin/variation/add', name: 'app_admin_variation_add', methods: ['POST'])]
    public function addVariation(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $variation = new PokemonVariation();
        $form = $this->createForm(PokemonVariationType::class, $variation);

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
                $this->pokeApiService->clearBasicListCache();
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
        $this->pokeApiService->clearBasicListCache();

        $this->addFlash('success', "Variação \"$name\" removida com sucesso!");

        return $this->redirectToRoute('app_admin_pokemon', ['_fragment' => 'variations-section']);
    }

    #[Route('/admin/evolution-rule/add', name: 'app_admin_evolution_rule_add', methods: ['POST'])]
    public function addEvolutionRule(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $rule = new EvolutionRule();
        $form = $this->createForm(EvolutionRuleType::class, $rule);

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
                'evolvedPokemon' => $evolvedPokemon,
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
            $this->addFlash('error', 'Erro durante a importação: '.$e->getMessage());
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
                'message' => "Processamento concluído. $count registros de $resource foram criados ou atualizados.",
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
                    'evolvedPokemon' => $evolvedPokemon,
                ]);

                if (!$rule) {
                    $rule = new EvolutionRule();
                    $rule->setBasePokemon($basePokemon);
                    $rule->setEvolvedPokemon($evolvedPokemon);
                }

                $rule->setMethod($method);
                $rule->setGender($dbGender);
                $this->entityManager->persist($rule);
                ++$count;
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
                ++$count;
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
                    'locationName' => $locNameInput,
                ]);

                if (!$loc) {
                    $loc = new PokemonLocation();
                    $loc->setPokemonName($pokemonName);
                    $loc->setLocationName($locNameInput);
                }

                $loc->setIsApproved($isApproved);
                $this->entityManager->persist($loc);
                ++$count;
            }
        } else {
            throw new \InvalidArgumentException("Tipo de recurso '$resource' não suportado para importação em lote.");
        }

        $this->entityManager->flush();

        if ($resource === 'variations') {
            $this->pokeApiService->clearBasicListCache();
        }

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
            '-disguised', '-busted',
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

    #[Route('/admin/moveset/{id}/approve', name: 'app_admin_moveset_approve', methods: ['POST'])]
    public function approveMoveset(int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $moveset = $this->entityManager->getRepository(Moveset::class)->find($id);
        if (!$moveset) {
            $this->addFlash('error', 'Moveset não encontrado.');

            return $this->redirectToRoute('app_admin_pokemon', ['tab' => 'movesets']);
        }

        $moveset->setIsDefault(true);
        $moveset->setSuggestedDefault(false);

        // Limpa a flag padrão dos outros movesets do mesmo pokemon e tipo
        $others = $this->entityManager->getRepository(Moveset::class)->findBy([
            'pokemonName' => $moveset->getPokemonName(),
            'type' => $moveset->getType(),
        ]);
        foreach ($others as $other) {
            if ($other->getId() !== $moveset->getId()) {
                $other->setIsDefault(false);
            }
        }

        $this->entityManager->flush();

        $this->addFlash('success', 'Sugestão aprovada! Moveset definido como padrão oficial.');

        return $this->redirectToRoute('app_admin_pokemon', ['tab' => 'movesets']);
    }

    #[Route('/admin/moveset/{id}/reject', name: 'app_admin_moveset_reject', methods: ['POST'])]
    public function rejectMoveset(int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $moveset = $this->entityManager->getRepository(Moveset::class)->find($id);
        if (!$moveset) {
            $this->addFlash('error', 'Moveset não encontrado.');

            return $this->redirectToRoute('app_admin_pokemon', ['tab' => 'movesets']);
        }

        $moveset->setSuggestedDefault(false);
        $this->entityManager->flush();

        $this->addFlash('success', 'Sugestão de padrão rejeitada. O moveset continuará disponível como secundário.');

        return $this->redirectToRoute('app_admin_pokemon', ['tab' => 'movesets']);
    }

    #[Route('/admin/moveset/{id}/default', name: 'app_admin_moveset_default', methods: ['POST'])]
    public function setDefaultMoveset(int $id, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $moveset = $this->entityManager->getRepository(Moveset::class)->find($id);
        if (!$moveset) {
            $this->addFlash('error', 'Moveset não encontrado.');

            return $this->redirectToRoute('app_admin_pokemon');
        }

        // Seta isDefault = true para esse moveset
        $moveset->setIsDefault(true);

        // Seta isDefault = false para os outros movesets do mesmo pokemon e tipo
        $others = $this->entityManager->getRepository(Moveset::class)->findBy([
            'pokemonName' => $moveset->getPokemonName(),
            'type' => $moveset->getType(),
        ]);
        foreach ($others as $other) {
            if ($other->getId() !== $moveset->getId()) {
                $other->setIsDefault(false);
            }
        }

        $this->entityManager->flush();

        $this->addFlash('success', 'Moveset definido como padrão oficial com sucesso!');

        $referer = $request->headers->get('referer');
        if ($referer) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('app_pokemon_detail', ['name' => $moveset->getPokemonName()]);
    }

    #[Route('/admin/moveset/{id}/remove-default', name: 'app_admin_moveset_remove_default', methods: ['POST'])]
    public function removeDefaultMoveset(int $id, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $moveset = $this->entityManager->getRepository(Moveset::class)->find($id);
        if (!$moveset) {
            $this->addFlash('error', 'Moveset não encontrado.');

            return $this->redirectToRoute('app_admin_pokemon');
        }

        $moveset->setIsDefault(false);
        $this->entityManager->flush();

        $this->addFlash('success', 'Moveset não é mais o padrão oficial.');

        $referer = $request->headers->get('referer');
        if ($referer) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('app_pokemon_detail', ['name' => $moveset->getPokemonName()]);
    }

    #[Route('/admin/moveset/bulk', name: 'app_admin_moveset_bulk', methods: ['POST'])]
    public function bulkMovesets(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $ids = $request->request->all('ids');
        $action = $request->request->get('action');

        if ($ids === [] || !is_array($ids)) {
            $this->addFlash('error', 'Nenhum moveset selecionado.');

            return $this->redirectToRoute('app_admin_pokemon', ['tab' => 'movesets']);
        }

        $repo = $this->entityManager->getRepository(Moveset::class);
        $count = 0;

        foreach ($ids as $id) {
            $moveset = $repo->find((int) $id);
            if ($moveset) {
                if ($action === 'approve') {
                    $moveset->setIsDefault(true);
                    $moveset->setSuggestedDefault(false);

                    // Clear other default movesets of the same type and pokemon
                    $others = $repo->findBy([
                        'pokemonName' => $moveset->getPokemonName(),
                        'type' => $moveset->getType(),
                    ]);
                    foreach ($others as $other) {
                        if ($other->getId() !== $moveset->getId()) {
                            $other->setIsDefault(false);
                        }
                    }
                } elseif ($action === 'delete') {
                    // "Rejeitar" sets suggestedDefault to false instead of removing the moveset
                    $moveset->setSuggestedDefault(false);
                }
                ++$count;
            }
        }

        $this->entityManager->flush();

        if ($action === 'approve') {
            $this->addFlash('success', sprintf('%d sugestões de moveset aprovadas e definidas como padrão!', $count));
        } else {
            $this->addFlash('success', sprintf('%d sugestões de moveset padrão rejeitadas (permanecem visíveis como secundários).', $count));
        }

        return $this->redirectToRoute('app_admin_pokemon', ['tab' => 'movesets']);
    }

    #[Route('/admin/location/{id}/approve', name: 'app_location_approve', methods: ['POST'])]
    public function approveLocation(int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $location = $this->entityManager->getRepository(PokemonLocation::class)->find($id);
        if (!$location) {
            $this->addFlash('error', 'Localização não encontrada.');

            return $this->redirectToRoute('app_admin_pokemon', ['tab' => 'locations']);
        }

        $location->setIsApproved(true);
        $this->entityManager->flush();

        $this->addFlash('success', 'Localização aprovada com sucesso!');

        return $this->redirectToRoute('app_admin_pokemon', ['tab' => 'locations']);
    }

    #[Route('/admin/location/{id}/delete', name: 'app_location_delete', methods: ['POST'])]
    public function deleteLocation(int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $location = $this->entityManager->getRepository(PokemonLocation::class)->find($id);
        if ($location) {
            $this->entityManager->remove($location);
            $this->entityManager->flush();
            $this->addFlash('success', 'Localização removida com sucesso!');
        } else {
            $this->addFlash('error', 'Localização não encontrada.');
        }

        return $this->redirectToRoute('app_admin_pokemon', ['tab' => 'locations']);
    }

    #[Route('/admin/location/bulk', name: 'app_location_bulk', methods: ['POST'])]
    public function bulkLocations(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $ids = $request->request->all('ids');
        $action = $request->request->get('action');

        if ($ids === [] || !is_array($ids)) {
            $this->addFlash('error', 'Nenhuma localização selecionada.');

            return $this->redirectToRoute('app_admin_pokemon', ['tab' => 'locations']);
        }

        $repo = $this->entityManager->getRepository(PokemonLocation::class);
        $count = 0;

        foreach ($ids as $id) {
            $location = $repo->find((int) $id);
            if ($location) {
                if ($action === 'approve') {
                    $location->setIsApproved(true);
                } elseif ($action === 'delete') {
                    $this->entityManager->remove($location);
                }
                ++$count;
            }
        }

        $this->entityManager->flush();

        if ($action === 'approve') {
            $this->addFlash('success', sprintf('%d localizações aprovadas com sucesso!', $count));
        } else {
            $this->addFlash('success', sprintf('%d localizações rejeitadas e removidas com sucesso.', $count));
        }

        return $this->redirectToRoute('app_admin_pokemon', ['tab' => 'locations']);
    }

    #[Route('/admin/location/import-official', name: 'app_location_import_official', methods: ['POST'])]
    public function importOfficialLocations(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $pokemonName = strtolower(trim($request->request->get('pokemonName', '')));
        $locations = $request->request->all('locations');

        if (empty($pokemonName) || empty($locations) || !is_array($locations)) {
            $this->addFlash('error', 'Nenhuma localização selecionada para importação.');

            return $this->redirectToRoute('app_admin_pokemon', ['tab' => 'locations', 'pokemon' => $pokemonName]);
        }

        $count = 0;
        $repo = $this->entityManager->getRepository(PokemonLocation::class);

        foreach ($locations as $locName) {
            $locName = trim($locName);
            if (empty($locName)) {
                continue;
            }

            $existing = $repo->findOneBy([
                'pokemonName' => $pokemonName,
                'locationName' => $locName,
            ]);

            if (!$existing) {
                $loc = new PokemonLocation();
                $loc->setPokemonName($pokemonName);
                $loc->setLocationName($locName);
                $loc->setIsApproved(true);
                $this->entityManager->persist($loc);
                ++$count;
            }
        }

        $this->entityManager->flush();

        $this->addFlash('success', sprintf('%d localizações oficiais importadas para %s com sucesso!', $count, ucfirst($pokemonName)));

        return $this->redirectToRoute('app_admin_pokemon', ['tab' => 'locations', 'pokemon' => $pokemonName]);
    }
}
