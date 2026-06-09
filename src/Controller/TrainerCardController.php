<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\MovesetRepository;
use App\Repository\UserRepository;
use App\Service\PokeApiService;
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
    private string $projectDir;

    // Avatares de recompensa bloqueados por medalhas de ouro
    public const AVATAR_REWARDS = [
        'Iris.png' => ['medal' => 'type_dragon', 'tier' => 'gold', 'label' => 'Medalha de Dragão de Ouro'],
        'Wattson.png' => ['medal' => 'type_electric', 'tier' => 'gold', 'label' => 'Medalha de Elétrico de Ouro'],
        'Marlon.png' => ['medal' => 'fisherman', 'tier' => 'gold', 'label' => 'Medalha de Pescador de Ouro'],
        'Roxie.png' => ['medal' => 'type_poison', 'tier' => 'gold', 'label' => 'Medalha de Venenoso de Ouro'],
        'Roxanne.png' => ['medal' => 'type_rock', 'tier' => 'gold', 'label' => 'Medalha de Pedra de Ouro'],
        'Steven.png' => ['medal' => 'hoenn', 'tier' => 'gold', 'label' => 'Medalha de Hoenn de Ouro'],
        'Winona.png' => ['medal' => 'type_flying', 'tier' => 'gold', 'label' => 'Medalha de Voador de Ouro'],
        'Flannery.png' => ['medal' => 'type_fire', 'tier' => 'gold', 'label' => 'Medalha de Fogo de Ouro'],
        'Brawly.png' => ['medal' => 'type_fighting', 'tier' => 'gold', 'label' => 'Medalha de Lutador de Ouro'],
        'Wallace.png' => ['medal' => 'type_water', 'tier' => 'gold', 'label' => 'Medalha de Água de Ouro'],
        'Benga.png' => ['medal' => 'unova', 'tier' => 'gold', 'label' => 'Medalha de Unova de Ouro'],
        'Ghetsis.png' => ['medal' => 'legendary', 'tier' => 'gold', 'label' => 'Medalha de Mestre Lendário de Ouro'],
        'Colress.png' => ['medal' => 'collector', 'tier' => 'gold', 'label' => 'Medalha de Colecionador de TMs de Ouro'],
        'Zinzolin.png' => ['medal' => 'type_ice', 'tier' => 'gold', 'label' => 'Medalha de Gelo de Ouro'],
        'Bellelba.png' => ['medal' => 'vivillon', 'tier' => 'gold', 'label' => 'Medalha de Coleção Vivillon de Ouro'],
        'Brycenman.png' => ['medal' => 'vivillon', 'tier' => 'gold', 'label' => 'Medalha de Coleção Vivillon de Ouro'],
        'Tate.png' => ['medal' => 'type_psychic', 'tier' => 'gold', 'label' => 'Medalha de Psíquico de Ouro'],
        'Liza.png' => ['medal' => 'type_fairy', 'tier' => 'gold', 'label' => 'Medalha de Fada de Ouro'],
        'Juan.png' => ['medal' => 'type_normal', 'tier' => 'gold', 'label' => 'Medalha de Normal de Ouro'],
        'Rood.png' => ['medal' => 'type_grass', 'tier' => 'gold', 'label' => 'Medalha de Grama de Ouro'],
        'Shadow_Triad.png' => ['medal' => 'type_dark', 'tier' => 'gold', 'label' => 'Medalha de Sombrio de Ouro'],
    ];

    // Títulos de conquista baseados em medalhas e marcos
    public const TITLES_CONFIG = [
        'novato' => [
            'name' => 'Treinador Novato',
            'ribbon' => 'alert-ribbon.png',
            'requirement' => 'Desbloqueado por padrão.',
            'check' => null,
        ],
        'cientista' => [
            'name' => 'Cientista de Elite',
            'ribbon' => 'effort-ribbon.png',
            'requirement' => 'Medalha "Cientista" no nível Bronze.',
            'check' => ['medal' => 'creator', 'tier' => 'bronze'],
        ],
        'pesquisador' => [
            'name' => 'Pesquisador de Elite',
            'ribbon' => 'classic-ribbon.png',
            'requirement' => 'Medalha "Pesquisador Pokémon" no nível Prata.',
            'check' => ['medal' => 'pokedex', 'tier' => 'silver'],
        ],
        'aclamado' => [
            'name' => 'Querido da Galera',
            'ribbon' => 'best-friends-ribbon.png',
            'requirement' => 'Medalha "Treinador Aclamado" no nível Bronze.',
            'check' => ['medal' => 'acclaimed', 'tier' => 'bronze'],
        ],
        'idolo' => [
            'name' => 'Ídolo do PokeFlaton',
            'ribbon' => 'gorgeous-royal-ribbon.png',
            'requirement' => 'Medalha "Treinador Aclamado" no nível Ouro.',
            'check' => ['medal' => 'acclaimed', 'tier' => 'gold'],
        ],
        'pescador' => [
            'name' => 'Mestre Pescador',
            'ribbon' => 'souvenir-ribbon.png',
            'requirement' => 'Medalha "Pescador" no nível Prata.',
            'check' => ['medal' => 'fisherman', 'tier' => 'silver'],
        ],
        'vivillon' => [
            'name' => 'Artista Vivillon',
            'ribbon' => 'artist-ribbon.png',
            'requirement' => 'Medalha "Coleção Vivillon" no nível Ouro.',
            'check' => ['medal' => 'vivillon', 'tier' => 'gold'],
        ],
        'colecionador' => [
            'name' => 'Mestre da Torre de TMs',
            'ribbon' => 'tower-master-ribbon.png',
            'requirement' => 'Medalha "Colecionador de TMs" no nível Ouro.',
            'check' => ['medal' => 'collector', 'tier' => 'gold'],
        ],
        'campeao_galar' => [
            'name' => 'Campeão de Galar',
            'ribbon' => 'galar-champion-ribbon.png',
            'requirement' => 'Medalha regional de "Galar/Hisui" no nível Ouro.',
            'check' => ['medal' => 'galar', 'tier' => 'gold'],
        ],
        'campeao_unova' => [
            'name' => 'Campeão de Unova',
            'ribbon' => 'champion-ribbon.png',
            'requirement' => 'Medalha regional de "Unova" no nível Ouro.',
            'check' => ['medal' => 'unova', 'tier' => 'gold'],
        ],
        'ranger' => [
            'name' => 'Ranger Pokémon',
            'ribbon' => 'battle-champion-ribbon.png',
            'requirement' => 'Medalha "Gotta Catch Em All" no nível Prata.',
            'check' => ['medal' => 'gotta-catch-all', 'tier' => 'silver'],
        ],
        'mestre' => [
            'name' => 'Mestre Pokémon',
            'ribbon' => 'master-rank-ribbon.png',
            'requirement' => 'Ter pelo menos 5 medalhas de Ouro.',
            'check' => '5_gold',
        ]
    ];

    public function __construct(
        EntityManagerInterface $entityManager,
        PokeApiService $pokeApiService,
        MovesetRepository $movesetRepository,
        UserRepository $userRepository,
        #[Autowire('%kernel.project_dir%')] string $projectDir
    ) {
        $this->entityManager = $entityManager;
        $this->pokeApiService = $pokeApiService;
        $this->movesetRepository = $movesetRepository;
        $this->userRepository = $userRepository;
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

        // 1. Obter dados unificados de perfil e conquistas
        $data = $this->getTrainerProfileData($user);

        // 2. Obter lista de TMs mapeadas
        $tmsJsonPath = $this->projectDir . '/scratch/tms.json';
        $tms = [];
        if (file_exists($tmsJsonPath)) {
            $tms = json_decode(file_get_contents($tmsJsonPath), true) ?? [];
        }

        // 3. Obter status de avatares com desbloqueio
        $avatarStatuses = $this->getAvatarUnlockStatus($user, [
            $data['activityMedals'],
            $data['catchMedals'],
            $data['regionalMedals'],
            $data['typeMedals']
        ]);

        // 4. Obter status de títulos com desbloqueio
        $titleStatuses = $this->getTitlesUnlockStatus($user, [
            $data['activityMedals'],
            $data['catchMedals'],
            $data['regionalMedals'],
            $data['typeMedals']
        ]);

        $selectedTitle = 'Treinador Novato';
        $selectedRibbon = 'https://raw.githubusercontent.com/msikma/pokesprite/master/misc/ribbon/gen8/alert-ribbon.png';
        foreach ($titleStatuses as $ts) {
            if ($ts['isSelected']) {
                $selectedTitle = $ts['name'];
                $selectedRibbon = $ts['ribbonUrl'];
                break;
            }
        }

        return $this->render('trainer_card/index.html.twig', [
            'user' => $user,
            'avatarStatuses' => $avatarStatuses,
            'titleStatuses' => $titleStatuses,
            'selectedTitle' => $selectedTitle,
            'selectedRibbon' => $selectedRibbon,
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

        $trainersJsonPath = $this->projectDir . '/scratch/trainers.json';
        $trainers = [];
        if (file_exists($trainersJsonPath)) {
            $trainers = json_decode(file_get_contents($trainersJsonPath), true) ?? [];
        }

        if (!in_array($avatar, $trainers)) {
            return new JsonResponse(['error' => 'Avatar inválido.'], Response::HTTP_BAD_REQUEST);
        }

        // Validar se o avatar está desbloqueado para o usuário
        $data = $this->getTrainerProfileData($user);
        $avatarStatuses = $this->getAvatarUnlockStatus($user, [
            $data['activityMedals'],
            $data['catchMedals'],
            $data['regionalMedals'],
            $data['typeMedals']
        ]);

        foreach ($avatarStatuses as $status) {
            if ($status['filename'] === $avatar) {
                if ($status['isLocked']) {
                    return new JsonResponse([
                        'error' => 'Este avatar está bloqueado! Requisito: ' . $status['requirement']
                    ], Response::HTTP_BAD_REQUEST);
                }
                break;
            }
        }

        $user->setAvatar($avatar);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'avatarUrl' => 'https://raw.githubusercontent.com/smogon/sprites/master/src/_uncategorized/canonical/trainers/gen5/black2-white2/' . $avatar
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
                $allPatterns = ["archipelago", "continental", "elegant", "garden", "high-plains", "icy-snow", "jungle", "marine", "meadow", "modern", "monsoon", "ocean", "polar", "river", "sandstorm", "savanna", "sun", "tundra", "fancy", "poke-ball"];
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

        // Pad array to 4 slots
        while (count($showcaseMedals) < 4) {
            $showcaseMedals[] = null;
        }

        if (empty($medalName)) {
            // Remove medal from slot
            $showcaseMedals[$slot] = null;
        } else {
            // Validate medal exists and is earned
            $data = $this->getTrainerProfileData($user);
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

        // Clean null trailing
        $showcaseMedals = array_values(array_pad($showcaseMedals, 4, null));

        $user->setShowcaseMedals($showcaseMedals);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'showcaseMedals' => $showcaseMedals
        ]);
    }

    #[Route('/trainer/{username}', name: 'app_trainer_profile', methods: ['GET'])]
    public function publicProfile(string $username): Response
    {
        $targetUser = $this->userRepository->findOneBy(['username' => $username]);
        if (!$targetUser) {
            throw $this->createNotFoundException('Treinador não encontrado.');
        }

        // 1. Obter dados unificados de perfil e conquistas
        $data = $this->getTrainerProfileData($targetUser);

        $titleStatuses = $this->getTitlesUnlockStatus($targetUser, [
            $data['activityMedals'],
            $data['catchMedals'],
            $data['regionalMedals'],
            $data['typeMedals']
        ]);

        $selectedTitle = 'Treinador Novato';
        $selectedRibbon = 'https://raw.githubusercontent.com/msikma/pokesprite/master/misc/ribbon/gen8/alert-ribbon.png';
        foreach ($titleStatuses as $ts) {
            if ($ts['isSelected']) {
                $selectedTitle = $ts['name'];
                $selectedRibbon = $ts['ribbonUrl'];
                break;
            }
        }

        return $this->render('trainer_card/public.html.twig', [
            'targetUser' => $targetUser,
            'selectedTitle' => $selectedTitle,
            'selectedRibbon' => $selectedRibbon,
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

    /**
     * Calcula e agrupa as estatísticas e medalhas de um treinador
     */
    private function getTrainerProfileData(User $user): array
    {
        // 1. Movesets criados e curtidas recebidas
        $userMovesets = $this->movesetRepository->findBy(['author' => $user->getUsername()]);
        $createdCount = count($userMovesets);
        
        $totalVotes = 0;
        $uniquePokemonNames = [];
        foreach ($userMovesets as $m) {
            $totalVotes += $m->getVotes();
            $uniquePokemonNames[$m->getPokemonName()] = true;
        }

        // 2. Tipos de Pokémons criados
        $uniqueTypesCreated = [];
        foreach (array_keys($uniquePokemonNames) as $pokeName) {
            try {
                $details = $this->pokeApiService->getPokemonDetails($pokeName);
                foreach ($details['types'] as $type) {
                    $uniqueTypesCreated[$type] = true;
                }
            } catch (\Exception $e) {
                // ignore
            }
        }
        $typesCreatedCount = count($uniqueTypesCreated);
        $tmsCount = count($user->getUnlockedTms());

        // 3. Pokémons Capturados e estatísticas detalhadas
        $caughtPokemon = $user->getCaughtPokemon();
        $caughtCount = count($caughtPokemon);

        $caughtTypes = [];
        $caughtDetails = [];
        $pikachuCaught = 0;
        $rattataCaught = 0;

        $regionCounts = [
            'kanto'  => 0,
            'johto'  => 0,
            'hoenn'  => 0,
            'sinnoh' => 0,
            'unova'  => 0,
            'kalos'  => 0,
            'alola'  => 0,
            'galar'  => 0,
            'paldea' => 0,
        ];

        foreach ($caughtPokemon as $caughtName) {
            try {
                $details = $this->pokeApiService->getPokemonDetails($caughtName);
                $id = $details['id'];
                $types = $details['types'];

                foreach ($types as $t) {
                    if (!isset($caughtTypes[$t])) {
                        $caughtTypes[$t] = 0;
                    }
                    $caughtTypes[$t]++;
                }

                $caughtDetails[] = [
                    'name' => $details['name'],
                    'id' => $id,
                    'sprite' => $details['sprite_official'],
                    'types' => $types
                ];

                // Checagens de capturas especiais
                if (str_contains(strtolower($caughtName), 'pikachu')) {
                    $pikachuCaught++;
                }
                if (str_contains(strtolower($caughtName), 'rattata')) {
                    $rattataCaught++;
                }

                // Checagens regionais
                $gen = PokeApiService::getGenerationById($id);
                if ($gen === 1) $regionCounts['kanto']++;
                elseif ($gen === 2) $regionCounts['johto']++;
                elseif ($gen === 3) $regionCounts['hoenn']++;
                elseif ($gen === 4) $regionCounts['sinnoh']++;
                elseif ($gen === 5) $regionCounts['unova']++;
                elseif ($gen === 6) $regionCounts['kalos']++;
                elseif ($gen === 7) $regionCounts['alola']++;
                elseif ($gen === 8) $regionCounts['galar']++;
                elseif ($gen === 9) $regionCounts['paldea']++;

            } catch (\Exception $e) {
                // ignore
            }
        }

        $waterCaught = $caughtTypes['water'] ?? 0;
        $vivillonCount = count($user->getVivillonPatterns());
        $followingCount = count($user->getFollowing());

        // Contar seguidores
        $qb = $this->userRepository->createQueryBuilder('u');
        $followersCount = (int) $qb->select('count(u.id)')
            ->where('u.following LIKE :username')
            ->setParameter('username', '%"' . $user->getUsername() . '"%')
            ->getQuery()
            ->getSingleScalarResult();

        $followersList = $this->userRepository->createQueryBuilder('u')
            ->where('u.following LIKE :username')
            ->setParameter('username', '%"' . $user->getUsername() . '"%')
            ->getQuery()
            ->getResult();

        // 4. Carregar configuração de medalhas ativas (admin)
        $medalsConfigPath = $this->projectDir . '/scratch/medals_config.json';
        $enabledMedals = [];
        $enabledVivillonPatterns = [];
        if (file_exists($medalsConfigPath)) {
            $config = json_decode(file_get_contents($medalsConfigPath), true) ?? [];
            $enabledMedals = $config['enabled_medals'] ?? [];
            $enabledVivillonPatterns = $config['enabled_vivillon_patterns'] ?? [];
        }

        // 5. Calcular medalhas por categoria
        // Categoria 1: Atividade e Comunidade
        $activityMedals = [
            $this->getMedalStatus('creator',        'Cientista',             'Crie novos movesets recomendados para Pokémons.',              $createdCount,   1,  5,   15,  'fa-flask',        'general', 'scientist',      $enabledMedals),
            $this->getMedalStatus('acclaimed',       'Treinador Aclamado',   'Soma total de curtidas recebidas em seus movesets.',            $totalVotes,     5,  25,  100, 'fa-heart',        'general', 'rising-star',    $enabledMedals),
            $this->getMedalStatus('collector',       'Colecionador de TMs',  'Quantidade de TMs registradas em sua mochila.',                $tmsCount,       10, 30,  60,  'fa-compact-disc', 'general', 'collector',      $enabledMedals),
            $this->getMedalStatus('friendship',      'Laço de Amizade',      'Quantidade de outros treinadores que você está seguindo.',      $followingCount, 2,  5,   15,  'fa-people-group', 'general', 'friend-finder',  $enabledMedals),
            $this->getMedalStatus('popular',         'Estrela da Comunidade','Quantidade de treinadores que seguem seu perfil.',             $followersCount, 1,  3,   10,  'fa-star',         'general', 'idol',           $enabledMedals),
            $this->getMedalStatus('gotta-catch-all', 'Gotta Catch Em All',   'Capture o maior número de espécies de Pokémon que puder!',     $caughtCount,    50, 150, 300, 'fa-trophy',       'general', 'pokemon-ranger', $enabledMedals),
        ];

        // Categoria 2: Enciclopédia
        $catchMedals = [
            $this->getMedalStatus('pokedex',   'Pesquisador Pokémon', 'Número de espécies de Pokémon capturadas e registradas.',      $caughtCount,   5, 20, 50, 'fa-database',  'general', 'ace-trainer',        $enabledMedals),
            $this->getMedalStatus('fisherman', 'Pescador',            'Quantidade de Pokémon do tipo Água capturados.',               $waterCaught,   3, 10, 25, 'fa-fish-fins', 'general', 'fisher',             $enabledMedals),
            $this->getMedalStatus('vivillon',  'Coleção Vivillon',    'Diferentes padrões de Vivillon capturados ao redor do mundo.', $vivillonCount, 3, 8,  15, 'fa-leaf',      'general', 'vivillon-collector', $enabledMedals),
            $this->getMedalStatus('pikachu',   'Fã de Pikachu',       'Capture um Pikachu para provar sua admiração.',                $pikachuCaught, 1, 2,  3,  'fa-bolt',      'general', 'pikachu-fan',        $enabledMedals),
            $this->getMedalStatus('youngster', 'Estilo Jovem',        'Capture um Rattata (o melhor Rattata!).',                      $rattataCaught, 1, 2,  3,  'fa-paw',       'general', 'gentleman',          $enabledMedals),
        ];

        // Categoria 3: Pokedéx Regional
        $regionalMedals = [
            $this->getMedalStatus('kanto',  'Kanto',       'Espécies descobertas na região de Kanto.',        $regionCounts['kanto'],  3, 10, 30, 'fa-map-location-dot', 'general', 'kanto',  $enabledMedals),
            $this->getMedalStatus('johto',  'Johto',       'Espécies descobertas na região de Johto.',        $regionCounts['johto'],  3, 10, 30, 'fa-map-location-dot', 'general', 'johto',  $enabledMedals),
            $this->getMedalStatus('hoenn',  'Hoenn',       'Espécies descobertas na região de Hoenn.',        $regionCounts['hoenn'],  3, 10, 30, 'fa-map-location-dot', 'general', 'hoenn',  $enabledMedals),
            $this->getMedalStatus('sinnoh', 'Sinnoh',      'Espécies descobertas na região de Sinnoh.',       $regionCounts['sinnoh'], 3, 10, 30, 'fa-map-location-dot', 'general', 'sinnoh', $enabledMedals),
            $this->getMedalStatus('unova',  'Unova',       'Espécies descobertas na região de Unova.',        $regionCounts['unova'],  3, 10, 30, 'fa-map-location-dot', 'general', 'unova',  $enabledMedals),
            $this->getMedalStatus('kalos',  'Kalos',       'Espécies descobertas na região de Kalos.',        $regionCounts['kalos'],  3, 8,  20, 'fa-map-location-dot', 'general', 'kalos',  $enabledMedals),
            $this->getMedalStatus('alola',  'Alola',       'Espécies descobertas na região de Alola.',        $regionCounts['alola'],  3, 8,  20, 'fa-map-location-dot', 'general', 'alola',  $enabledMedals),
            $this->getMedalStatus('galar',  'Galar/Hisui', 'Espécies descobertas na região de Galar e Hisui.',$regionCounts['galar'],  3, 8,  20, 'fa-map-location-dot', 'general', 'galar',  $enabledMedals),
            $this->getMedalStatus('paldea', 'Paldea',      'Espécies descobertas na região de Paldea.',       $regionCounts['paldea'], 3, 8,  20, 'fa-map-location-dot', 'general', 'paldea', $enabledMedals),
        ];

        // Categoria 4: Especialistas em Tipos
        // Tipo => [title, desc, icon, badgeSlug (para o repo de medalhas)]
        $typeMedalNames = [
            'normal'   => ['title' => 'Estudante',          'desc' => 'Normal',    'icon' => 'fa-circle',              'badge' => 'schoolkid'],
            'fire'     => ['title' => 'Esquentado',         'desc' => 'Fogo',      'icon' => 'fa-fire',                'badge' => 'kindler'],
            'water'    => ['title' => 'Nadador',            'desc' => 'Água',      'icon' => 'fa-droplet',             'badge' => 'swimmer'],
            'grass'    => ['title' => 'Jardineiro',         'desc' => 'Grama',     'icon' => 'fa-leaf',                'badge' => 'gardener'],
            'electric' => ['title' => 'Roqueiro',           'desc' => 'Elétrico',  'icon' => 'fa-bolt-lightning',      'badge' => 'rocker'],
            'ice'      => ['title' => 'Esquiador',          'desc' => 'Gelo',      'icon' => 'fa-snowflake',           'badge' => 'skier'],
            'fighting' => ['title' => 'Cinturão Negro',     'desc' => 'Lutador',   'icon' => 'fa-hand-fist',           'badge' => 'black-belt'],
            'poison'   => ['title' => 'Garota Punk',        'desc' => 'Venenoso',  'icon' => 'fa-skull-crossbones',    'badge' => 'punk-girl'],
            'ground'   => ['title' => 'Maníaco das Ruínas', 'desc' => 'Terra',     'icon' => 'fa-mountain-sun',        'badge' => 'ruin-maniac'],
            'flying'   => ['title' => 'Ornitólogo',         'desc' => 'Voador',    'icon' => 'fa-wind',                'badge' => 'bird-keeper'],
            'psychic'  => ['title' => 'Médium',             'desc' => 'Psíquico',  'icon' => 'fa-eye',                 'badge' => 'psychic'],
            'bug'      => ['title' => 'Caçador de Insetos', 'desc' => 'Inseto',    'icon' => 'fa-bug',                 'badge' => 'bug-catcher'],
            'rock'     => ['title' => 'Montanhista',        'desc' => 'Pedra',     'icon' => 'fa-gem',                 'badge' => 'hiker'],
            'ghost'    => ['title' => 'Místico',            'desc' => 'Fantasma',  'icon' => 'fa-ghost',               'badge' => 'hex-maniac'],
            'dragon'   => ['title' => 'Domador de Dragões', 'desc' => 'Dragão',    'icon' => 'fa-dragon',              'badge' => 'dragon-tamer'],
            'steel'    => ['title' => 'Agente do Pátio',    'desc' => 'Aço',       'icon' => 'fa-shield',              'badge' => 'rail-staff'],
            'dark'     => ['title' => 'Delinquente',        'desc' => 'Sombrio',   'icon' => 'fa-moon',                'badge' => 'delinquent'],
            'fairy'    => ['title' => 'Conto de Fadas',     'desc' => 'Fada',      'icon' => 'fa-wand-magic-sparkles', 'badge' => 'fairy-tale-girl'],
        ];

        $typeMedals = [];
        foreach ($typeMedalNames as $typeKey => $meta) {
            $count = $caughtTypes[$typeKey] ?? 0;
            $medal = $this->getMedalStatus(
                'type_' . $typeKey,
                $meta['title'],
                'Capture Pokémon do tipo ' . $meta['desc'] . '.',
                $count,
                2, 5, 12,
                $meta['icon'],
                'type',
                $meta['badge'],
                $enabledMedals
            );
            $typeMedals[] = $medal;
        }

        // Categoria 5: Vivillon Patterns Individuais
        $vivillonPatternData = [
            'meadow'      => ['label' => 'Meadow',      'sprite' => 'https://raw.githubusercontent.com/PokeMiners/pogo_assets/master/Images/Badges/Vivillon/meadow.png'],
            'archipelago' => ['label' => 'Archipelago', 'sprite' => 'https://raw.githubusercontent.com/PokeMiners/pogo_assets/master/Images/Badges/Vivillon/archipelago.png'],
            'continental' => ['label' => 'Continental', 'sprite' => 'https://raw.githubusercontent.com/PokeMiners/pogo_assets/master/Images/Badges/Vivillon/continental.png'],
            'elegant'     => ['label' => 'Elegant',     'sprite' => 'https://raw.githubusercontent.com/PokeMiners/pogo_assets/master/Images/Badges/Vivillon/elegant.png'],
            'garden'      => ['label' => 'Garden',      'sprite' => 'https://raw.githubusercontent.com/PokeMiners/pogo_assets/master/Images/Badges/Vivillon/garden.png'],
            'high-plains' => ['label' => 'High Plains', 'sprite' => 'https://raw.githubusercontent.com/PokeMiners/pogo_assets/master/Images/Badges/Vivillon/high_plains.png'],
            'icy-snow'    => ['label' => 'Icy Snow',    'sprite' => 'https://raw.githubusercontent.com/PokeMiners/pogo_assets/master/Images/Badges/Vivillon/icy_snow.png'],
            'jungle'      => ['label' => 'Jungle',      'sprite' => 'https://raw.githubusercontent.com/PokeMiners/pogo_assets/master/Images/Badges/Vivillon/jungle.png'],
            'marine'      => ['label' => 'Marine',      'sprite' => 'https://raw.githubusercontent.com/PokeMiners/pogo_assets/master/Images/Badges/Vivillon/marine.png'],
            'modern'      => ['label' => 'Modern',      'sprite' => 'https://raw.githubusercontent.com/PokeMiners/pogo_assets/master/Images/Badges/Vivillon/modern.png'],
            'monsoon'     => ['label' => 'Monsoon',     'sprite' => 'https://raw.githubusercontent.com/PokeMiners/pogo_assets/master/Images/Badges/Vivillon/monsoon.png'],
            'ocean'       => ['label' => 'Ocean',       'sprite' => 'https://raw.githubusercontent.com/PokeMiners/pogo_assets/master/Images/Badges/Vivillon/ocean.png'],
            'polar'       => ['label' => 'Polar',       'sprite' => 'https://raw.githubusercontent.com/PokeMiners/pogo_assets/master/Images/Badges/Vivillon/polar.png'],
            'river'       => ['label' => 'River',       'sprite' => 'https://raw.githubusercontent.com/PokeMiners/pogo_assets/master/Images/Badges/Vivillon/river.png'],
            'sandstorm'   => ['label' => 'Sandstorm',   'sprite' => 'https://raw.githubusercontent.com/PokeMiners/pogo_assets/master/Images/Badges/Vivillon/sandstorm.png'],
            'savanna'     => ['label' => 'Savanna',     'sprite' => 'https://raw.githubusercontent.com/PokeMiners/pogo_assets/master/Images/Badges/Vivillon/savanna.png'],
            'sun'         => ['label' => 'Sun',         'sprite' => 'https://raw.githubusercontent.com/PokeMiners/pogo_assets/master/Images/Badges/Vivillon/sun.png'],
            'tundra'      => ['label' => 'Tundra',      'sprite' => 'https://raw.githubusercontent.com/PokeMiners/pogo_assets/master/Images/Badges/Vivillon/tundra.png'],
            'fancy'       => ['label' => 'Fancy',       'sprite' => 'https://raw.githubusercontent.com/PokeMiners/pogo_assets/master/Images/Badges/Vivillon/fancy.png'],
            'poke-ball'   => ['label' => 'Poké Ball',   'sprite' => 'https://raw.githubusercontent.com/PokeMiners/pogo_assets/master/Images/Badges/Vivillon/pokeball.png'],
        ];

        $userVivillonPatterns = $user->getVivillonPatterns();
        $vivillonMedals = [];
        foreach ($vivillonPatternData as $patternKey => $patternMeta) {
            $isEnabled = in_array($patternKey, $enabledVivillonPatterns);
            $hasPattern = in_array($patternKey, $userVivillonPatterns);
            $vivillonMedals[] = [
                'key'      => $patternKey,
                'label'    => $patternMeta['label'],
                'sprite'   => $patternMeta['sprite'],
                'unlocked' => $hasPattern,
                'enabled'  => $isEnabled,
            ];
        }

        return [
            'createdCount'   => $createdCount,
            'totalVotes'     => $totalVotes,
            'typesCount'     => $typesCreatedCount,
            'tmsCount'       => $tmsCount,
            'caughtCount'    => $caughtCount,
            'followingCount' => $followingCount,
            'followersCount' => $followersCount,
            'caughtDetails'  => $caughtDetails,
            'activityMedals' => $activityMedals,
            'catchMedals'    => $catchMedals,
            'regionalMedals' => $regionalMedals,
            'typeMedals'     => $typeMedals,
            'vivillonMedals' => $vivillonMedals,
            'userMovesets'   => $userMovesets,
            'followersList'  => $followersList,
        ];
    }

    /**
     * Auxiliar para obter o status de bloqueio dos avatares com base nas medalhas
     */
    public function getAvatarUnlockStatus(User $user, array $computedMedalGroups): array
    {
        $trainersJsonPath = $this->projectDir . '/scratch/trainers.json';
        $trainers = [];
        if (file_exists($trainersJsonPath)) {
            $trainers = json_decode(file_get_contents($trainersJsonPath), true) ?? [];
        }

        // Indexa as medalhas por nome para busca rápida
        $medalsByName = [];
        foreach ($computedMedalGroups as $group) {
            foreach ($group as $medal) {
                $medalsByName[$medal['name']] = $medal['tier'];
            }
        }

        $avatarStatuses = [];
        foreach ($trainers as $trainer) {
            $isLocked = false;
            $requirement = null;

            if (isset(self::AVATAR_REWARDS[$trainer])) {
                $req = self::AVATAR_REWARDS[$trainer];
                $reqMedal = $req['medal'];
                $reqTier = $req['tier'];

                $userTier = $medalsByName[$reqMedal] ?? 'locked';
                
                // Pesos dos tiers para comparação
                $tierWeights = ['locked' => 0, 'bronze' => 1, 'silver' => 2, 'gold' => 3];
                $userWeight = $tierWeights[$userTier] ?? 0;
                $reqWeight = $tierWeights[$reqTier] ?? 3;

                if ($userWeight < $reqWeight) {
                    $isLocked = true;
                    $requirement = $req['label'];
                }
            }

            $avatarStatuses[] = [
                'filename' => $trainer,
                'name' => str_replace(['.png', '-', '_'], ['', ' ', ' '], $trainer),
                'isLocked' => $isLocked,
                'requirement' => $requirement
            ];
        }

        return $avatarStatuses;
    }

    /**
     * Calcula o status de uma única medalha com base em seus milestones.
     * $badgeCategory: 'general' ou 'type' (para buscar no repo KovuTheHusky/pokemon-medals)
     * $badgeSlug: nome do arquivo sem extensão (ex: 'scientist', 'dragon-tamer')
     * $enabledMedals: lista de medals ativas via admin (array vazio = nenhum filtro)
     */
    private function getMedalStatus(
        string $name,
        string $title,
        string $description,
        int $current,
        int $bronze,
        int $silver,
        int $gold,
        string $icon,
        string $badgeCategory = 'general',
        string $badgeSlug = '',
        array $enabledMedals = []
    ): array {
        $baseUrl = 'https://raw.githubusercontent.com/KovuTheHusky/pokemon-medals/main/';

        // Se a lista de medals ativas não está vazia e esta medal não está nela → force locked
        $adminLocked = !empty($enabledMedals) && !in_array($name, $enabledMedals);

        if ($adminLocked) {
            $tier        = 'locked';
            $nextTarget  = $bronze;
            $percent     = 0;
        } elseif ($current >= $gold) {
            $tier = 'gold';
            $nextTarget = null;
            $percent = 100;
        } elseif ($current >= $silver) {
            $tier = 'silver';
            $nextTarget = $gold;
            $percent = min(100, (int) (($current - $silver) / ($gold - $silver) * 100));
        } elseif ($current >= $bronze) {
            $tier = 'bronze';
            $nextTarget = $silver;
            $percent = min(100, (int) (($current - $bronze) / ($silver - $bronze) * 100));
        } else {
            $tier = 'locked';
            $nextTarget = $bronze;
            $percent = min(100, (int) ($current / $bronze * 100));
        }

        // Montar URL do badge: locked usa bronze tier visualmente
        $imgTier = ($tier === 'locked') ? 'bronze' : $tier;
        $badgeImg = $badgeSlug
            ? $baseUrl . $badgeCategory . '/' . $imgTier . '/' . $badgeSlug . '.webp'
            : null;

        return [
            'name'        => $name,
            'title'       => $title,
            'description' => $description,
            'current'     => $current,
            'tier'        => $tier,
            'nextTarget'  => $nextTarget,
            'percent'     => $percent,
            'icon'        => $icon,
            'badgeImg'    => $badgeImg,
            'enabled'     => !$adminLocked,
            'milestones'  => [
                'bronze' => $bronze,
                'silver' => $silver,
                'gold'   => $gold
            ]
        ];
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
        $data = $this->getTrainerProfileData($user);
        $titleStatuses = $this->getTitlesUnlockStatus($user, [
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

    /**
     * Retorna o status de desbloqueio dos títulos do usuário com base em suas medalhas
     */
    public function getTitlesUnlockStatus(User $user, array $computedMedalGroups): array
    {
        $medalsByName = [];
        $goldCount = 0;
        foreach ($computedMedalGroups as $group) {
            foreach ($group as $medal) {
                $medalsByName[$medal['name']] = $medal['tier'];
                if ($medal['tier'] === 'gold') {
                    $goldCount++;
                }
            }
        }

        $titleStatuses = [];
        $selectedTitle = $user->getTitle();

        foreach (self::TITLES_CONFIG as $key => $config) {
            $isLocked = false;

            if ($config['check'] !== null) {
                if ($config['check'] === '5_gold') {
                    if ($goldCount < 5) {
                        $isLocked = true;
                    }
                } else {
                    $reqMedal = $config['check']['medal'];
                    $reqTier = $config['check']['tier'];
                    $userTier = $medalsByName[$reqMedal] ?? 'locked';

                    $tierWeights = ['locked' => 0, 'bronze' => 1, 'silver' => 2, 'gold' => 3];
                    $userWeight = $tierWeights[$userTier] ?? 0;
                    $reqWeight = $tierWeights[$reqTier] ?? 1;

                    if ($userWeight < $reqWeight) {
                        $isLocked = true;
                    }
                }
            }

            $ribbonUrl = $config['ribbon']
                ? 'https://raw.githubusercontent.com/msikma/pokesprite/master/misc/ribbon/gen8/' . $config['ribbon']
                : null;

            $titleStatuses[] = [
                'key' => $key,
                'name' => $config['name'],
                'ribbon' => $config['ribbon'],
                'ribbonUrl' => $ribbonUrl,
                'isLocked' => $isLocked,
                'requirement' => $config['requirement'],
                'isSelected' => ($selectedTitle === $config['name']) || ($selectedTitle === null && $key === 'novato'),
            ];
        }

        return $titleStatuses;
    }
}
