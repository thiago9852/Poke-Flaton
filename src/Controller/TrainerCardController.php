<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\VivillonPattern;
use App\Repository\MovesetRepository;
use App\Repository\UserRepository;
use App\Service\PokeApiService;
use App\Service\TrainerProfileService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TrainerCardController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private PokeApiService $pokeApiService;
    private MovesetRepository $movesetRepository;
    private UserRepository $userRepository;
    private TrainerProfileService $trainerProfileService;
    private string $projectDir;

    public function __construct(
        EntityManagerInterface $entityManager,
        PokeApiService $pokeApiService,
        MovesetRepository $movesetRepository,
        UserRepository $userRepository,
        TrainerProfileService $trainerProfileService,
        #[Autowire('%kernel.project_dir%')] string $projectDir
    ) {
        $this->entityManager = $entityManager;
        $this->pokeApiService = $pokeApiService;
        $this->movesetRepository = $movesetRepository;
        $this->userRepository = $userRepository;
        $this->trainerProfileService = $trainerProfileService;
        $this->projectDir = $projectDir;
    }

    #[Route('/trainer-card', name: 'app_trainer_card', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Obter dados unificados de perfil e conquistas via Service
        $data = $this->trainerProfileService->getTrainerProfileData($user);

        // Obter lista de TMs mapeadas
        $tmsJsonPath = $this->projectDir . '/scratch/tms.json';
        $tms = [];
        if (file_exists($tmsJsonPath)) {
            $tms = json_decode(file_get_contents($tmsJsonPath), true) ?? [];
        }

        // Obter status de avatares com desbloqueio
        $avatarStatuses = $this->trainerProfileService->getAvatarUnlockStatus($user, [
            $data['activityMedals'],
            $data['catchMedals'],
            $data['regionalMedals'],
            $data['typeMedals']
        ]);

        // Obter status de avatares Pokémon
        $pkmAvatarStatuses = $this->trainerProfileService->getPkmAvatarStatuses($user);

        // Obter status de títulos com desbloqueio
        $titleStatuses = $this->trainerProfileService->getTitlesUnlockStatus($user, [
            $data['activityMedals'],
            $data['catchMedals'],
            $data['regionalMedals'],
            $data['typeMedals']
        ]);

        $selectedTitle = 'Treinador Novato';
        $selectedTitleRequirement = 'Desbloqueado por padrão.';
        $selectedRibbon = 'https://raw.githubusercontent.com/msikma/pokesprite/master/misc/ribbon/gen8/alert-ribbon.png';
        foreach ($titleStatuses as $ts) {
            if ($ts['isSelected']) {
                $selectedTitle = $ts['name'];
                $selectedTitleRequirement = $ts['requirement'];
                $selectedRibbon = $ts['ribbonUrl'];
                break;
            }
        }

        $templateStatuses = $this->trainerProfileService->getTemplatesUnlockStatus($user, [
            $data['activityMedals'],
            $data['catchMedals'],
            $data['regionalMedals'],
            $data['typeMedals']
        ]);

        $selectedTemplateUrl = null;
        foreach ($templateStatuses as $ts) {
            if ($ts['isSelected']) {
                $selectedTemplateUrl = $ts['imageUrl'];
                break;
            }
        }

        return $this->render('trainer_card/index.html.twig', [
            'user' => $user,
            'avatarStatuses' => $avatarStatuses,
            'pkmAvatarStatuses' => $pkmAvatarStatuses,
            'titleStatuses' => $titleStatuses,
            'templateStatuses' => $templateStatuses,
            'selectedTitle' => $selectedTitle,
            'selectedTitleRequirement' => $selectedTitleRequirement,
            'selectedRibbon' => $selectedRibbon,
            'selectedTemplateUrl' => $selectedTemplateUrl,
            'tms' => $tms,
            'activityMedals' => $data['activityMedals'],
            'catchMedals' => $data['catchMedals'],
            'regionalMedals' => $data['regionalMedals'],
            'typeMedals' => $data['typeMedals'],
            'vivillonMedals' => $data['vivillonMedals'],
            'createdCount' => $data['createdCount'],
            'totalVotes' => $data['totalVotes'],
            'typesCount' => $data['typesCount'],
            'tmsCount' => $data['tmsCount'],
            'caughtCount' => $data['caughtCount'],
            'followingCount' => $data['followingCount'],
            'followersCount' => $data['followersCount'],
            'caughtDetails' => $data['caughtDetails'],
            'userMovesets' => $data['userMovesets'],
            'followersList' => $data['followersList'],
        ]);
    }

    #[Route('/tm/toggle', name: 'app_trainer_card_tm_toggle', methods: ['POST'])]
    public function toggleTm(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Acesso negado.'], Response::HTTP_FORBIDDEN);
        }

        $moveName = $request->request->get('move');
        if (empty($moveName)) {
            return new JsonResponse(['error' => 'Parâmetro inválido.'], Response::HTTP_BAD_REQUEST);
        }

        $unlockedTms = $user->getUnlockedTms();
        $key = array_search($moveName, $unlockedTms);

        if ($key !== false) {
            unset($unlockedTms[$key]);
            $unlockedTms = array_values($unlockedTms);
            $unlocked = false;
        } else {
            $unlockedTms[] = $moveName;
            $unlocked = true;
        }

        $user->setUnlockedTms($unlockedTms);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'unlocked' => $unlocked,
            'count' => count($unlockedTms)
        ]);
    }

    #[Route('/avatar/update', name: 'app_trainer_card_avatar_update', methods: ['POST'])]
    public function updateAvatar(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Acesso negado.'], Response::HTTP_FORBIDDEN);
        }

        $avatar = $request->request->get('avatar');
        if (empty($avatar)) {
            return new JsonResponse(['error' => 'Parâmetro inválido.'], Response::HTTP_BAD_REQUEST);
        }

        $prefix = 'trainer';
        $filename = $avatar;
        if (str_contains($avatar, ':')) {
            $parts = explode(':', $avatar, 2);
            $prefix = $parts[0];
            $filename = $parts[1];
        } else {
            $avatar = 'trainer:' . $avatar;
        }

        if ($prefix !== 'pkm' && $prefix !== 'trainer') {
            return new JsonResponse(['error' => 'Tipo de avatar inválido.'], Response::HTTP_BAD_REQUEST);
        }

        $data = $this->trainerProfileService->getTrainerProfileData($user);
        if ($prefix === 'trainer') {
            $avatarStatuses = $this->trainerProfileService->getAvatarUnlockStatus($user, [
                $data['activityMedals'],
                $data['catchMedals'],
                $data['regionalMedals'],
                $data['typeMedals']
            ]);
        } else {
            $avatarStatuses = $this->trainerProfileService->getPkmAvatarStatuses($user, [
                $data['activityMedals'],
                $data['catchMedals'],
                $data['regionalMedals'],
                $data['typeMedals']
            ]);
        }

        $found = false;
        foreach ($avatarStatuses as $status) {
            if ($status['filename'] === $avatar) {
                $found = true;
                if ($status['isLocked']) {
                    return new JsonResponse([
                        'error' => 'Este avatar está bloqueado! Requisito: ' . $status['requirement']
                    ], Response::HTTP_BAD_REQUEST);
                }
                break;
            }
        }

        if (!$found) {
            return new JsonResponse(['error' => 'Avatar não disponível ou inválido.'], Response::HTTP_BAD_REQUEST);
        }

        $user->setAvatar($avatar);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'avatarUrl' => $this->trainerProfileService->getAvatarUrl($avatar)
        ]);
    }

    #[Route('/pokemon/toggle-catch', name: 'app_trainer_card_pokemon_toggle_catch', methods: ['POST'])]
    public function toggleCatch(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Acesso negado.'], Response::HTTP_FORBIDDEN);
        }

        $pokemonName = trim($request->request->get('name', ''));
        if (empty($pokemonName)) {
            return new JsonResponse(['error' => 'Nome do Pokémon inválido.'], Response::HTTP_BAD_REQUEST);
        }

        $caught = $user->getCaughtPokemon();
        $key = array_search($pokemonName, $caught);
        $isCaught = false;
        $newPattern = null;

        if ($key !== false) {
            unset($caught[$key]);
            $caught = array_values($caught);
        } else {
            $caught[] = $pokemonName;
            $isCaught = true;

            // Vivillon patterns collection
            if ($pokemonName === 'vivillon') {
                $allPatterns = VivillonPattern::values();
                $userPatterns = $user->getVivillonPatterns();
                $missingPatterns = array_diff($allPatterns, $userPatterns);
                if (!empty($missingPatterns)) {
                    $newPattern = $missingPatterns[array_rand($missingPatterns)];
                    $userPatterns[] = $newPattern;
                    $user->setVivillonPatterns($userPatterns);
                }
            }
        }

        $user->setCaughtPokemon($caught);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'caught' => $isCaught,
            'pattern' => $newPattern,
            'count' => count($caught)
        ]);
    }

    #[Route('/follow/toggle', name: 'app_trainer_card_follow_toggle', methods: ['POST'])]
    public function toggleFollow(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Acesso negado.'], Response::HTTP_FORBIDDEN);
        }

        $targetUsername = trim($request->request->get('username', ''));
        if (empty($targetUsername)) {
            return new JsonResponse(['error' => 'Nome de usuário inválido.'], Response::HTTP_BAD_REQUEST);
        }

        if ($targetUsername === $user->getUsername()) {
            return new JsonResponse(['error' => 'Você não pode seguir a si mesmo.'], Response::HTTP_BAD_REQUEST);
        }

        $targetUser = $this->userRepository->findOneBy(['username' => $targetUsername]);
        if (!$targetUser) {
            return new JsonResponse(['error' => 'Treinador não encontrado.'], Response::HTTP_NOT_FOUND);
        }

        $following = $user->getFollowing();
        $key = array_search($targetUsername, $following);
        $isFollowing = false;

        if ($key !== false) {
            unset($following[$key]);
            $following = array_values($following);
        } else {
            $following[] = $targetUsername;
            $isFollowing = true;
        }

        $user->setFollowing($following);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'following' => $isFollowing,
            'count' => count($following)
        ]);
    }

    #[Route('/showcase-medals/update', name: 'app_trainer_card_showcase_update', methods: ['POST'])]
    public function updateShowcaseMedals(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Acesso negado.'], Response::HTTP_FORBIDDEN);
        }

        $slot = (int) $request->request->get('slot', -1);
        $medalName = trim($request->request->get('medal', ''));

        if ($slot < 0 || $slot > 3) {
            return new JsonResponse(['error' => 'Slot inválido.'], Response::HTTP_BAD_REQUEST);
        }

        $showcaseMedals = $user->getShowcaseMedals();

        // Preenche o array até ter 4 slots
        while (count($showcaseMedals) < 4) {
            $showcaseMedals[] = null;
        }

        if (empty($medalName)) {
            // Remove a medal do slot
            $showcaseMedals[$slot] = null;
        } else {
            // Valida se a medalha existe e foi conquistada
            $data = $this->trainerProfileService->getTrainerProfileData($user);
            $allMedals = array_merge(
                $data['activityMedals'],
                $data['catchMedals'],
                $data['regionalMedals'],
                $data['typeMedals']
            );

            $validMedal = false;
            foreach ($allMedals as $medal) {
                if ($medal['name'] === $medalName && $medal['tier'] !== 'locked' && $medal['enabled']) {
                    $validMedal = true;
                    break;
                }
            }

            if (!$validMedal) {
                return new JsonResponse(['error' => 'Medalha inválida ou ainda não conquistada.'], Response::HTTP_BAD_REQUEST);
            }

            $showcaseMedals[$slot] = $medalName;
        }

        // Limpa null trailing
        $showcaseMedals = array_values(array_pad($showcaseMedals, 4, null));

        $user->setShowcaseMedals($showcaseMedals);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'showcaseMedals' => $showcaseMedals
        ]);
    }

    #[Route('/trainers/ranking', name: 'app_trainer_ranking', methods: ['GET'])]
    public function ranking(Request $request): Response
    {
        $search = trim($request->query->get('q', ''));
        $sortBy = $request->query->get('sort', 'medals'); // 'medalha' ou 'likes'

        if (!empty($search)) {
            $users = $this->userRepository->createQueryBuilder('u')
                ->where('u.username LIKE :q')
                ->setParameter('q', '%' . $search . '%')
                ->getQuery()
                ->getResult();
        } else {
            $users = $this->userRepository->findAll();
        }

        $rankedUsers = [];
        foreach ($users as $u) {
            $data = $this->trainerProfileService->getTrainerProfileData($u);

            // conta as medalhas
            $goldCount = 0;
            $silverCount = 0;
            $bronzeCount = 0;

            $allMedals = array_merge(
                $data['activityMedals'],
                $data['catchMedals'],
                $data['regionalMedals'],
                $data['typeMedals']
            );

            foreach ($allMedals as $medal) {
                if ($medal['tier'] === 'gold') $goldCount++;
                elseif ($medal['tier'] === 'silver') $silverCount++;
                elseif ($medal['tier'] === 'bronze') $bronzeCount++;
            }

            // Pega o título selecionado
            $titleStatuses = $this->trainerProfileService->getTitlesUnlockStatus($u, [
                $data['activityMedals'],
                $data['catchMedals'],
                $data['regionalMedals'],
                $data['typeMedals']
            ]);

            $selectedTitle = 'Treinador Novato';
            $selectedRibbon = $this->trainerProfileService->getRibbonUrl('alert-ribbon.png');
            foreach ($titleStatuses as $ts) {
                if ($ts['isSelected']) {
                    $selectedTitle = $ts['name'];
                    $selectedRibbon = $ts['ribbonUrl'];
                    break;
                }
            }

            $rankedUsers[] = [
                'user' => $u,
                'gold' => $goldCount,
                'silver' => $silverCount,
                'bronze' => $bronzeCount,
                'totalMedals' => $goldCount + $silverCount + $bronzeCount,
                'votes' => $data['totalVotes'],
                'created' => $data['createdCount'],
                'followers' => $data['followersCount'],
                'title' => $selectedTitle,
                'titleRibbon' => $selectedRibbon,
            ];
        }

        // Ordenação
        if ($sortBy === 'likes') {
            usort($rankedUsers, function ($a, $b) {
                if ($a['votes'] !== $b['votes']) {
                    return $b['votes'] <=> $a['votes'];
                }
                return $b['gold'] <=> $a['gold'];
            });
        } else { // Ordena por medalhas
            usort($rankedUsers, function ($a, $b) {
                if ($a['gold'] !== $b['gold']) {
                    return $b['gold'] <=> $a['gold'];
                }
                if ($a['silver'] !== $b['silver']) {
                    return $b['silver'] <=> $a['silver'];
                }
                if ($a['bronze'] !== $b['bronze']) {
                    return $b['bronze'] <=> $a['bronze'];
                }
                return $b['votes'] <=> $a['votes'];
            });
        }

        // Recuperar títulos do Top 3 dinamicamente do banco de dados (ou fallback)
        $titleRepo = $this->entityManager->getRepository(\App\Entity\Title::class);
        $topTitles = $titleRepo->findBy(['reqRankType' => $sortBy], ['reqRankPos' => 'ASC']);

        $firstTitle = $sortBy === 'likes' ? 'Lenda da Popularidade' : 'Campeão Supremo';
        $firstRibbon = $this->trainerProfileService->getRibbonUrl($sortBy === 'likes' ? 'royal-ribbon.png' : 'championship-ribbon.png');

        $secondTitle = $sortBy === 'likes' ? 'Ícone da Comunidade' : 'Mestre de Elite';
        $secondRibbon = $this->trainerProfileService->getRibbonUrl($sortBy === 'likes' ? 'red-ribbon.png' : 'elite-four-ribbon.png');

        $thirdTitle = $sortBy === 'likes' ? 'Querido do Público' : 'Especialista Lendário';
        $thirdRibbon = $this->trainerProfileService->getRibbonUrl($sortBy === 'likes' ? 'best-friends-ribbon.png' : 'classic-ribbon.png');

        foreach ($topTitles as $t) {
            if ($t->getReqRankPos() === 1) {
                $firstTitle = $t->getName();
                $firstRibbon = $this->trainerProfileService->getRibbonUrl($t->getRibbon());
            } elseif ($t->getReqRankPos() === 2) {
                $secondTitle = $t->getName();
                $secondRibbon = $this->trainerProfileService->getRibbonUrl($t->getRibbon());
            } elseif ($t->getReqRankPos() === 3) {
                $thirdTitle = $t->getName();
                $thirdRibbon = $this->trainerProfileService->getRibbonUrl($t->getRibbon());
            }
        }

        $renderParams = [
            'rankedUsers' => $rankedUsers,
            'search' => $search,
            'sort' => $sortBy,
            'first_title' => $firstTitle,
            'first_title_ribbon' => $firstRibbon,
            'second_title' => $secondTitle,
            'second_title_ribbon' => $secondRibbon,
            'third_title' => $thirdTitle,
            'third_title_ribbon' => $thirdRibbon,
        ];

        if ($request->query->get('ajax') || $request->isXmlHttpRequest()) {
            return $this->render('trainer_card/_ranking_content.html.twig', $renderParams);
        }

        return $this->render('trainer_card/ranking.html.twig', $renderParams);
    }

    #[Route('/trainer/{username}', name: 'app_trainer_profile', methods: ['GET'])]
    public function publicProfile(string $username): Response
    {
        $targetUser = $this->userRepository->findOneBy(['username' => $username]);
        if (!$targetUser) {
            throw $this->createNotFoundException('Treinador não encontrado.');
        }

        // Obter dados unificados de perfil e conquistas
        $data = $this->trainerProfileService->getTrainerProfileData($targetUser);

        // Obter lista de TMs mapeadas
        $tmsJsonPath = $this->projectDir . '/scratch/tms.json';
        $tms = [];
        if (file_exists($tmsJsonPath)) {
            $tms = json_decode(file_get_contents($tmsJsonPath), true) ?? [];
        }

        $titleStatuses = $this->trainerProfileService->getTitlesUnlockStatus($targetUser, [
            $data['activityMedals'],
            $data['catchMedals'],
            $data['regionalMedals'],
            $data['typeMedals']
        ]);

        $selectedTitle = 'Treinador Novato';
        $selectedTitleRequirement = 'Desbloqueado por padrão.';
        $selectedRibbon = 'https://raw.githubusercontent.com/msikma/pokesprite/master/misc/ribbon/gen8/alert-ribbon.png';
        foreach ($titleStatuses as $ts) {
            if ($ts['isSelected']) {
                $selectedTitle = $ts['name'];
                $selectedTitleRequirement = $ts['requirement'];
                $selectedRibbon = $ts['ribbonUrl'];
                break;
            }
        }

        $templateStatuses = $this->trainerProfileService->getTemplatesUnlockStatus($targetUser, [
            $data['activityMedals'],
            $data['catchMedals'],
            $data['regionalMedals'],
            $data['typeMedals']
        ]);

        $selectedTemplateUrl = null;
        foreach ($templateStatuses as $ts) {
            if ($ts['isSelected']) {
                $selectedTemplateUrl = $ts['imageUrl'];
                break;
            }
        }

        return $this->render('trainer_card/public.html.twig', [
            'targetUser' => $targetUser,
            'selectedTitle' => $selectedTitle,
            'selectedTitleRequirement' => $selectedTitleRequirement,
            'selectedRibbon' => $selectedRibbon,
            'selectedTemplateUrl' => $selectedTemplateUrl,
            'tms' => $tms,
            'activityMedals' => $data['activityMedals'],
            'catchMedals' => $data['catchMedals'],
            'regionalMedals' => $data['regionalMedals'],
            'typeMedals' => $data['typeMedals'],
            'vivillonMedals' => $data['vivillonMedals'],
            'createdCount' => $data['createdCount'],
            'totalVotes' => $data['totalVotes'],
            'typesCount' => $data['typesCount'],
            'tmsCount' => $data['tmsCount'],
            'caughtCount' => $data['caughtCount'],
            'followingCount' => $data['followingCount'],
            'followersCount' => $data['followersCount'],
            'caughtDetails' => $data['caughtDetails'],
        ]);
    }

    #[Route('/title/update', name: 'app_trainer_card_title_update', methods: ['POST'])]
    public function updateTitle(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Acesso negado.'], Response::HTTP_FORBIDDEN);
        }

        $title = $request->request->get('title');
        if (empty($title)) {
            return new JsonResponse(['error' => 'Parâmetro inválido.'], Response::HTTP_BAD_REQUEST);
        }

        // Validar se o título está desbloqueado para o usuário
        $data = $this->trainerProfileService->getTrainerProfileData($user);
        $titleStatuses = $this->trainerProfileService->getTitlesUnlockStatus($user, [
            $data['activityMedals'],
            $data['catchMedals'],
            $data['regionalMedals'],
            $data['typeMedals']
        ]);

        $validTitle = false;
        $ribbonUrl = null;
        foreach ($titleStatuses as $status) {
            if ($status['name'] === $title) {
                if ($status['isLocked']) {
                    return new JsonResponse([
                        'error' => 'Este título está bloqueado! Requisito: ' . $status['requirement']
                    ], Response::HTTP_BAD_REQUEST);
                }
                $validTitle = true;
                $ribbonUrl = $status['ribbonUrl'];
                break;
            }
        }

        if (!$validTitle) {
            return new JsonResponse(['error' => 'Título inválido.'], Response::HTTP_BAD_REQUEST);
        }

        $user->setTitle($title);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'title' => $title,
            'ribbonUrl' => $ribbonUrl
        ]);
    }

    #[Route('/template/update', name: 'app_trainer_card_template_update', methods: ['POST'])]
    public function updateTemplate(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Acesso negado.'], Response::HTTP_FORBIDDEN);
        }

        $templateImage = $request->request->get('template');
        if (empty($templateImage)) {
            $user->setCardTemplate(null);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
            return new JsonResponse(['success' => true, 'imageUrl' => null]);
        }

        $data = $this->trainerProfileService->getTrainerProfileData($user);
        $templateStatuses = $this->trainerProfileService->getTemplatesUnlockStatus($user, [
            $data['activityMedals'],
            $data['catchMedals'],
            $data['regionalMedals'],
            $data['typeMedals']
        ]);

        $validTemplate = false;
        $imageUrl = null;
        foreach ($templateStatuses as $status) {
            if ($status['image'] === $templateImage) {
                if ($status['isLocked']) {
                    return new JsonResponse([
                        'error' => 'Este plano de fundo está bloqueado! Requisito: ' . $status['requirement']
                    ], Response::HTTP_BAD_REQUEST);
                }
                $validTemplate = true;
                $imageUrl = $status['imageUrl'];
                break;
            }
        }

        if (!$validTemplate) {
            return new JsonResponse(['error' => 'Plano de fundo inválido.'], Response::HTTP_BAD_REQUEST);
        }

        $user->setCardTemplate($templateImage);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'imageUrl' => $imageUrl
        ]);
    }
}
