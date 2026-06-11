<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\MovesetRepository;
use App\Repository\UserRepository;
use App\Repository\TitleRepository;
use App\Enum\Medal;
use App\Enum\VivillonPattern;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class TrainerProfileService
{
    private EntityManagerInterface $entityManager;
    private PokeApiService $pokeApiService;
    private MovesetRepository $movesetRepository;
    private UserRepository $userRepository;
    private TitleRepository $titleRepository;
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

    public function __construct(
        EntityManagerInterface $entityManager,
        PokeApiService $pokeApiService,
        MovesetRepository $movesetRepository,
        UserRepository $userRepository,
        TitleRepository $titleRepository,
        #[Autowire('%kernel.project_dir%')] string $projectDir
    ) {
        $this->entityManager = $entityManager;
        $this->pokeApiService = $pokeApiService;
        $this->movesetRepository = $movesetRepository;
        $this->userRepository = $userRepository;
        $this->titleRepository = $titleRepository;
        $this->projectDir = $projectDir;
    }

    /**
     * Garante a criação da tabela "title" e popula os registros padrão caso a tabela esteja vazia.
     */
    public function initializeDatabaseAndTitles(): void
    {
        $connection = $this->entityManager->getConnection();
        $schemaManager = $connection->createSchemaManager();

        if (!$schemaManager->tablesExist(['title'])) {
            $sql = "CREATE TABLE title (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, 
                name VARCHAR(255) NOT NULL, 
                ribbon VARCHAR(255) NOT NULL, 
                requirement VARCHAR(255) NOT NULL, 
                req_medal VARCHAR(255) DEFAULT NULL, 
                req_tier VARCHAR(255) DEFAULT NULL, 
                req_gold_count INTEGER DEFAULT NULL, 
                is_default BOOLEAN DEFAULT 0 NOT NULL
            )";
            $connection->executeStatement($sql);
        }

        // Se estiver vazia, popula com os títulos padrão
        $titlesCount = (int) $connection->fetchOne("SELECT COUNT(*) FROM title");
        if ($titlesCount === 0) {
            $defaultTitles = [
                [
                    'name' => 'Treinador Novato',
                    'ribbon' => 'alert-ribbon.png',
                    'requirement' => 'Desbloqueado por padrão.',
                    'req_medal' => null,
                    'req_tier' => null,
                    'req_gold_count' => null,
                    'is_default' => 1
                ],
                [
                    'name' => 'Cientista de Elite',
                    'ribbon' => 'effort-ribbon.png',
                    'requirement' => 'Medalha "Cientista" no nível Bronze.',
                    'req_medal' => 'creator',
                    'req_tier' => 'bronze',
                    'req_gold_count' => null,
                    'is_default' => 0
                ],
                [
                    'name' => 'Pesquisador de Elite',
                    'ribbon' => 'classic-ribbon.png',
                    'requirement' => 'Medalha "Pesquisador Pokémon" no nível Prata.',
                    'req_medal' => 'pokedex',
                    'req_tier' => 'silver',
                    'req_gold_count' => null,
                    'is_default' => 0
                ],
                [
                    'name' => 'Querido da Galera',
                    'ribbon' => 'best-friends-ribbon.png',
                    'requirement' => 'Medalha "Treinador Aclamado" no nível Bronze.',
                    'req_medal' => 'acclaimed',
                    'req_tier' => 'bronze',
                    'req_gold_count' => null,
                    'is_default' => 0
                ],
                [
                    'name' => 'Ídolo do PokeFlaton',
                    'ribbon' => 'gorgeous-royal-ribbon.png',
                    'requirement' => 'Medalha "Treinador Aclamado" no nível Ouro.',
                    'req_medal' => 'acclaimed',
                    'req_tier' => 'gold',
                    'req_gold_count' => null,
                    'is_default' => 0
                ],
                [
                    'name' => 'Mestre Pescador',
                    'ribbon' => 'souvenir-ribbon.png',
                    'requirement' => 'Medalha "Pescador" no nível Prata.',
                    'req_medal' => 'fisherman',
                    'req_tier' => 'silver',
                    'req_gold_count' => null,
                    'is_default' => 0
                ],
                [
                    'name' => 'Artista Vivillon',
                    'ribbon' => 'artist-ribbon.png',
                    'requirement' => 'Medalha "Coleção Vivillon" no nível Ouro.',
                    'req_medal' => 'vivillon',
                    'req_tier' => 'gold',
                    'req_gold_count' => null,
                    'is_default' => 0
                ],
                [
                    'name' => 'Mestre da Torre de TMs',
                    'ribbon' => 'tower-master-ribbon.png',
                    'requirement' => 'Medalha "Colecionador de TMs" no nível Ouro.',
                    'req_medal' => 'collector',
                    'req_tier' => 'gold',
                    'req_gold_count' => null,
                    'is_default' => 0
                ],
                [
                    'name' => 'Campeão de Galar',
                    'ribbon' => 'galar-champion-ribbon.png',
                    'requirement' => 'Medalha regional de "Galar/Hisui" no nível Ouro.',
                    'req_medal' => 'galar',
                    'req_tier' => 'gold',
                    'req_gold_count' => null,
                    'is_default' => 0
                ],
                [
                    'name' => 'Campeão de Unova',
                    'ribbon' => 'champion-ribbon.png',
                    'requirement' => 'Medalha regional de "Unova" no nível Ouro.',
                    'req_medal' => 'unova',
                    'req_tier' => 'gold',
                    'req_gold_count' => null,
                    'is_default' => 0
                ],
                [
                    'name' => 'Ranger Pokémon',
                    'ribbon' => 'battle-champion-ribbon.png',
                    'requirement' => 'Medalha "Gotta Catch Em All" no nível Prata.',
                    'req_medal' => 'gotta-catch-all',
                    'req_tier' => 'silver',
                    'req_gold_count' => null,
                    'is_default' => 0
                ],
                [
                    'name' => 'Mestre Pokémon',
                    'ribbon' => 'master-rank-ribbon.png',
                    'requirement' => 'Ter pelo menos 20 medalhas de Ouro.',
                    'req_medal' => null,
                    'req_tier' => null,
                    'req_gold_count' => 20,
                    'is_default' => 0
                ]
            ];

            foreach ($defaultTitles as $t) {
                $connection->insert('title', $t);
            }
        }
    }

    /**
     * Calcula e agrupa as estatísticas e medalhas de um treinador
     */
    public function getTrainerProfileData(User $user): array
    {
        $this->initializeDatabaseAndTitles();

        // 1. Movesets criados e curtidas recebidas
        $userMovesets = $this->movesetRepository->findBy(['author' => $user->getUsername()]);
        $createdCount = count($userMovesets);

        $totalVotes = 0;
        $uniquePokemonNames = [];
        foreach ($userMovesets as $m) {
            $totalVotes += $m->getVotes();
            $uniquePokemonNames[$m->getPokemonName()] = true;
        }

        // Batch pre-fetch Pokemon details to optimize performance (avoid N sequential API calls)
        $caughtPokemon = $user->getCaughtPokemon();
        $pokemonNamesToFetch = array_unique(array_merge(
            array_keys($uniquePokemonNames),
            $caughtPokemon
        ));
        $fetchedDetails = [];
        if (!empty($pokemonNamesToFetch)) {
            $fetchedDetails = $this->pokeApiService->getPokemonDetailsBatchByNames($pokemonNamesToFetch);
        }

        // 2. Tipos de Pokémons criados
        $uniqueTypesCreated = [];
        foreach (array_keys($uniquePokemonNames) as $pokeName) {
            try {
                $details = $fetchedDetails[strtolower($pokeName)] ?? $this->pokeApiService->getPokemonDetails($pokeName);
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
                $details = $fetchedDetails[strtolower($caughtName)] ?? $this->pokeApiService->getPokemonDetails($caughtName);
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

        // Títulos disponíveis para preencher recompensas
        $titles = $this->titleRepository->findAll();

        // 5. Calcular medalhas por categoria
        $getCurrentValue = fn(Medal $medal) => match ($medal) {
            Medal::Creator => $createdCount,
            Medal::Acclaimed => $totalVotes,
            Medal::Collector => $tmsCount,
            Medal::Friendship => $followingCount,
            Medal::Popular => $followersCount,
            Medal::GottaCatchAll => $caughtCount,
            Medal::Pokedex => $caughtCount,
            Medal::Fisherman => $waterCaught,
            Medal::Vivillon => $vivillonCount,
            Medal::Pikachu => $pikachuCaught,
            Medal::Youngster => $rattataCaught,
            Medal::Kanto => $regionCounts['kanto'],
            Medal::Johto => $regionCounts['johto'],
            Medal::Hoenn => $regionCounts['hoenn'],
            Medal::Sinnoh => $regionCounts['sinnoh'],
            Medal::Unova => $regionCounts['unova'],
            Medal::Kalos => $regionCounts['kalos'],
            Medal::Alola => $regionCounts['alola'],
            Medal::Galar => $regionCounts['galar'],
            Medal::Paldea => $regionCounts['paldea'],
            default => str_starts_with($medal->value, 'type_')
                ? ($caughtTypes[substr($medal->value, 5)] ?? 0)
                : 0,
        };

        $activityMedals = [];
        $catchMedals = [];
        $regionalMedals = [];
        $typeMedals = [];

        foreach (Medal::cases() as $medal) {
            $currentVal = $getCurrentValue($medal);
            $status = $this->getMedalStatus($medal, $currentVal, $enabledMedals, $titles, $user);

            switch ($medal->getGroupName()) {
                case 'Atividade e Comunidade':
                    $activityMedals[] = $status;
                    break;
                case 'Enciclopédia Pokémon':
                    $catchMedals[] = $status;
                    break;
                case 'Pokédex Regional':
                    $regionalMedals[] = $status;
                    break;
                case 'Captura por Tipo':
                    $typeMedals[] = $status;
                    break;
            }
        }

        // Categoria 5: Vivillon Patterns Individuais
        $userVivillonPatterns = $user->getVivillonPatterns();
        $vivillonMedals = [];
        foreach (VivillonPattern::cases() as $pattern) {
            $patternKey = $pattern->value;
            $isEnabled = in_array($patternKey, $enabledVivillonPatterns);
            $hasPattern = in_array($patternKey, $userVivillonPatterns);
            $vivillonMedals[] = [
                'key'      => $patternKey,
                'label'    => $pattern->getLabel(),
                'sprite'   => $pattern->getSpriteUrl(),
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
     * Auxiliar para mapear recompensas de medalhas (usando títulos da BD e avatares do código)
     */
    public function getMedalRewards(string $medalName, array $titles): array
    {
        $rewards = [];

        // Check AVATAR_REWARDS
        foreach (self::AVATAR_REWARDS as $filename => $req) {
            if ($req['medal'] === $medalName) {
                $tier = $req['tier'];
                $name = str_replace(['.png', '-', '_'], ['', ' ', ' '], $filename);
                $rewards[$tier][] = "Avatar: " . ucwords($name);
            }
        }

        // Check DB titles
        foreach ($titles as $title) {
            if ($title->getReqMedal() === $medalName) {
                $tier = $title->getReqTier() ?? 'bronze';
                $rewards[$tier][] = "Título: \"" . $title->getName() . "\"";
            }
        }

        return $rewards;
    }

    /**
     * Retorna o status de desbloqueio dos títulos do usuário com base em suas medalhas
     */
    public function getTitlesUnlockStatus(User $user, array $computedMedalGroups): array
    {
        $this->initializeDatabaseAndTitles();

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

        $titles = $this->titleRepository->findAll();
        $titleStatuses = [];
        $selectedTitle = $user->getTitle();

        foreach ($titles as $title) {
            $isLocked = false;

            if (!$title->isDefault()) {
                if ($title->getReqGoldCount() !== null) {
                    if ($goldCount < $title->getReqGoldCount()) {
                        $isLocked = true;
                    }
                } elseif ($title->getReqMedal() !== null) {
                    $reqMedal = $title->getReqMedal();
                    $reqTier = $title->getReqTier() ?? 'bronze';
                    $userTier = $medalsByName[$reqMedal] ?? 'locked';

                    $tierWeights = ['locked' => 0, 'bronze' => 1, 'silver' => 2, 'gold' => 3];
                    $userWeight = $tierWeights[$userTier] ?? 0;
                    $reqWeight = $tierWeights[$reqTier] ?? 1;

                    if ($userWeight < $reqWeight) {
                        $isLocked = true;
                    }
                }
            }

            $ribbon = $title->getRibbon();
            $ribbonUrl = null;
            if ($ribbon) {
                if (str_starts_with($ribbon, 'http://') || str_starts_with($ribbon, 'https://')) {
                    $ribbonUrl = $ribbon;
                } else {
                    $ribbonUrl = 'https://raw.githubusercontent.com/msikma/pokesprite/master/misc/ribbon/gen8/' . $ribbon;
                }
            }

            $titleStatuses[] = [
                'id' => $title->getId(),
                'name' => $title->getName(),
                'ribbon' => $ribbon,
                'ribbonUrl' => $ribbonUrl,
                'isLocked' => $isLocked,
                'requirement' => $title->getRequirement(),
                'isSelected' => ($selectedTitle === $title->getName()) || ($selectedTitle === null && $title->isDefault()),
            ];
        }

        return $titleStatuses;
    }

    /**
     * Calcula o status de uma única medalha com base em seus milestones.
     */
    private function getMedalStatus(
        Medal $medal,
        int $current,
        array $enabledMedals = [],
        array $titles = [],
        User $user = null
    ): array {
        $baseUrl = 'https://raw.githubusercontent.com/KovuTheHusky/pokemon-medals/main/';
        $name = $medal->value;
        $title = $medal->getTitle();
        $description = $medal->getDescription();
        $icon = $medal->getIcon();
        
        $milestones = $medal->getMilestones();
        $bronze = $milestones['bronze'];
        $silver = $milestones['silver'];
        $gold = $milestones['gold'];

        // Verificar se é uma medalha regional e se corresponde à regional de registro do usuário
        $isRegionalMedal = in_array($name, ['kanto', 'johto', 'hoenn', 'sinnoh', 'unova', 'kalos', 'alola', 'galar', 'paldea']);
        $regionalLocked = false;
        
        if ($isRegionalMedal && $user !== null) {
            $userRegion = strtolower($user->getRegional() ?? '');
            if ($name !== $userRegion) {
                $regionalLocked = true;
            }
        }

        // Se a lista de medalhas ativas não está vazia e esta medalha não está nela, ou se está bloqueada regionalmente → force locked
        $adminLocked = (!empty($enabledMedals) && !in_array($name, $enabledMedals)) || $regionalLocked;

        if ($adminLocked) {
            $tier        = 'locked';
            $nextTarget  = $bronze;
            $percent     = 0;
            if ($regionalLocked) {
                $description = 'Esta medalha está bloqueada pois sua região de registro é ' . ucfirst($user->getRegional()) . '.';
            } else {
                $description = 'Esta medalha está atualmente bloqueada/desativada pela administração.';
            }
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
        $badgeImg = $baseUrl . $medal->getBadgePath($imgTier);

        $rewards = $this->getMedalRewards($name, $titles);

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
            'rewards'     => $rewards,
            'milestones'  => [
                'bronze' => $bronze,
                'silver' => $silver,
                'gold'   => $gold
            ]
        ];
    }
}
