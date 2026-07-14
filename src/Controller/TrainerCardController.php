<?php

namespace App\Controller;

use App\Entity\TierList;
use App\Entity\User;
use App\Repository\UserRepository;
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
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly TrainerProfileService $trainerProfileService,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
    }

    #[Route('/trainer-card', name: 'app_trainer_card', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Obter dados unificados de perfil e estatísticas via Service
        $data = $this->trainerProfileService->getTrainerProfileData($user);

        // Obter lista de TMs mapeadas
        $tmsJsonPath = $this->projectDir.'/scratch/tms.json';
        $tms = [];
        if (file_exists($tmsJsonPath)) {
            $tms = json_decode(file_get_contents($tmsJsonPath), true) ?? [];
        }

        $avatarStatuses = $this->trainerProfileService->getAvatarUnlockStatus($user);
        $pkmAvatarStatuses = $this->trainerProfileService->getPkmAvatarStatuses($user);
        $templateStatuses = $this->trainerProfileService->getTemplatesUnlockStatus($user);

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
            'templateStatuses' => $templateStatuses,
            'selectedTemplateUrl' => $selectedTemplateUrl,
            'tms' => $tms,
            'createdCount' => $data['createdCount'],
            'totalVotes' => $data['totalVotes'],
            'typesCount' => $data['typesCount'],
            'tmsCount' => $data['tmsCount'],
            'caughtCount' => $data['caughtCount'],
            'followingCount' => $data['followingCount'],
            'followersCount' => $data['followersCount'],
            'caughtDetails' => $data['caughtDetails'],
            'userMovesets' => $data['userMovesets'],
            'userTierLists' => $this->entityManager->getRepository(TierList::class)->findBy(['user' => $user], ['createdAt' => 'DESC']),
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
            'count' => count($unlockedTms),
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
            $avatar = 'trainer:'.$avatar;
        }

        if ($prefix !== 'pkm' && $prefix !== 'trainer') {
            return new JsonResponse(['error' => 'Tipo de avatar inválido.'], Response::HTTP_BAD_REQUEST);
        }

        $avatarStatuses = $prefix === 'trainer'
            ? $this->trainerProfileService->getAvatarUnlockStatus($user)
            : $this->trainerProfileService->getPkmAvatarStatuses($user);

        $found = false;
        foreach ($avatarStatuses as $status) {
            if ($status['filename'] === $avatar) {
                $found = true;
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
            'avatarUrl' => $this->trainerProfileService->getAvatarUrl($avatar),
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

        if ($key !== false) {
            unset($caught[$key]);
            $caught = array_values($caught);
        } else {
            $caught[] = $pokemonName;
            $isCaught = true;
        }

        $user->setCaughtPokemon($caught);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'caught' => $isCaught,
            'count' => count($caught),
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
            'count' => count($following),
        ]);
    }

    #[Route('/trainer/{username}', name: 'app_trainer_profile', methods: ['GET'])]
    public function publicProfile(string $username): Response
    {
        $targetUser = $this->userRepository->findOneBy(['username' => $username]);
        if (!$targetUser) {
            throw $this->createNotFoundException('Treinador não encontrado.');
        }

        // Obter dados unificados de perfil e estatísticas
        $data = $this->trainerProfileService->getTrainerProfileData($targetUser);

        // Obter lista de TMs mapeadas
        $tmsJsonPath = $this->projectDir.'/scratch/tms.json';
        $tms = [];
        if (file_exists($tmsJsonPath)) {
            $tms = json_decode(file_get_contents($tmsJsonPath), true) ?? [];
        }

        $templateStatuses = $this->trainerProfileService->getTemplatesUnlockStatus($targetUser);

        $selectedTemplateUrl = null;
        foreach ($templateStatuses as $ts) {
            if ($ts['isSelected']) {
                $selectedTemplateUrl = $ts['imageUrl'];
                break;
            }
        }

        return $this->render('trainer_card/public.html.twig', [
            'targetUser' => $targetUser,
            'selectedTemplateUrl' => $selectedTemplateUrl,
            'tms' => $tms,
            'createdCount' => $data['createdCount'],
            'totalVotes' => $data['totalVotes'],
            'typesCount' => $data['typesCount'],
            'tmsCount' => $data['tmsCount'],
            'caughtCount' => $data['caughtCount'],
            'followingCount' => $data['followingCount'],
            'followersCount' => $data['followersCount'],
            'caughtDetails' => $data['caughtDetails'],
            'userMovesets' => $data['userMovesets'],
            'userTierLists' => $this->entityManager->getRepository(TierList::class)->findBy(['user' => $targetUser], ['createdAt' => 'DESC']),
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

        $templateStatuses = $this->trainerProfileService->getTemplatesUnlockStatus($user);

        $validTemplate = false;
        $imageUrl = null;
        foreach ($templateStatuses as $status) {
            if ($status['image'] === $templateImage) {
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
            'imageUrl' => $imageUrl,
        ]);
    }
}
