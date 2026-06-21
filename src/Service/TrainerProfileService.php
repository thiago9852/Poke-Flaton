<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\MovesetRepository;
use App\Repository\UserRepository;
use App\Repository\TitleRepository;
use App\Repository\CardTemplateRepository;
use App\Enum\Medal;
use App\Enum\VivillonPattern;
use App\Config\AvatarConfig;
use App\Config\TitleConfig;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TrainerProfileService
{
    private EntityManagerInterface $entityManager;
    private PokeApiService $pokeApiService;
    private MovesetRepository $movesetRepository;
    private UserRepository $userRepository;
    private TitleRepository $titleRepository;
    private CardTemplateRepository $cardTemplateRepository;
    private string $projectDir;
    private HttpClientInterface $httpClient;
    private ?array $cachedLikesRanking = null;
    private ?array $cachedMedalsRanking = null;
    private bool $titlesInitialized = false;
    private bool $avatarsInitialized = false;
    private bool $templatesInitialized = false;


    public function __construct(
        EntityManagerInterface $entityManager,
        PokeApiService $pokeApiService,
        MovesetRepository $movesetRepository,
        UserRepository $userRepository,
        TitleRepository $titleRepository,
        CardTemplateRepository $cardTemplateRepository,
        #[Autowire('%kernel.project_dir%')] string $projectDir,
        HttpClientInterface $httpClient
    ) {
        $this->entityManager = $entityManager;
        $this->pokeApiService = $pokeApiService;
        $this->movesetRepository = $movesetRepository;
        $this->userRepository = $userRepository;
        $this->titleRepository = $titleRepository;
        $this->cardTemplateRepository = $cardTemplateRepository;
        $this->projectDir = $projectDir;
        $this->httpClient = $httpClient;
    }

    /**
     * Garante a criação da tabela "title" e popula os registros padrão caso a tabela esteja vazia.
     */
    public function initializeDatabaseAndTitles(): void
    {
        if ($this->titlesInitialized) {
            return;
        }

        try {
            $count = $this->titleRepository->count([]);
            if ($count === 0) {
                $connection = $this->entityManager->getConnection();
                foreach (TitleConfig::DEFAULT_TITLES as $t) {
                    $connection->insert('title', $t);
                }
            }
        } catch (\Exception $e) {
            // Ignore
        }

        $this->titlesInitialized = true;
        $this->initializeMovesetColumns();
    }

    /**
     * Garante a criação das colunas is_approved e is_default na tabela moveset caso não existam.
     */
    public function initializeMovesetColumns(): void
    {
        try {
            $connection = $this->entityManager->getConnection();
            $schemaManager = $connection->createSchemaManager();
            if ($schemaManager->tablesExist(['moveset'])) {
                $columns = $schemaManager->listTableColumns('moveset');
                $hasApproved = false;
                $hasDefault = false;
                $hasSuggestedDefault = false;
                foreach ($columns as $column) {
                    if (strtolower($column->getName()) === 'is_approved') {
                        $hasApproved = true;
                    }
                    if (strtolower($column->getName()) === 'is_default') {
                        $hasDefault = true;
                    }
                    if (strtolower($column->getName()) === 'suggested_default') {
                        $hasSuggestedDefault = true;
                    }
                }
                
                if (!$hasApproved) {
                    $connection->executeStatement('ALTER TABLE moveset ADD is_approved TINYINT(1) DEFAULT 0 NOT NULL');
                    $connection->executeStatement('UPDATE moveset SET is_approved = 1');
                }
                if (!$hasDefault) {
                    $connection->executeStatement('ALTER TABLE moveset ADD is_default TINYINT(1) DEFAULT 0 NOT NULL');
                }
                if (!$hasSuggestedDefault) {
                    $connection->executeStatement('ALTER TABLE moveset ADD suggested_default TINYINT(1) DEFAULT 0 NOT NULL');
                }
            }
        } catch (\Exception $e) {
            // Ignore
        }
    }


    /**
     * Obtém a posição do usuário no ranking de curtidas e de medalhas 
     */
    public function getUserRankingPositions(User $user): array
    {
        if ($this->cachedLikesRanking !== null && $this->cachedMedalsRanking !== null) {
            return [
                'likes' => $this->cachedLikesRanking[$user->getId()] ?? 999,
                'medals' => $this->cachedMedalsRanking[$user->getId()] ?? 999
            ];
        }

        $users = $this->userRepository->findAll();
        $userData = [];

        foreach ($users as $u) {
            // Calcula os dados sem ranking para evitar recursão infinita
            $profileData = $this->getTrainerProfileData($u, false);
            
            $gold = 0;
            $silver = 0;
            $bronze = 0;
            
            $allMedals = array_merge(
                $profileData['activityMedals'],
                $profileData['catchMedals'],
                $profileData['regionalMedals'],
                $profileData['typeMedals']
            );
            
            foreach ($allMedals as $m) {
                if ($m['tier'] === 'gold') $gold++;
                elseif ($m['tier'] === 'silver') $silver++;
                elseif ($m['tier'] === 'bronze') $bronze++;
            }
            
            $userData[] = [
                'id' => $u->getId(),
                'votes' => $profileData['totalVotes'],
                'gold' => $gold,
                'silver' => $silver,
                'bronze' => $bronze
            ];
        }

        // Ordena para o ranking de curtidas (Likes)
        $likesSorted = $userData;
        usort($likesSorted, function ($a, $b) {
            if ($a['votes'] !== $b['votes']) {
                return $b['votes'] <=> $a['votes'];
            }
            return $b['gold'] <=> $a['gold'];
        });

        // Ordena para o ranking de medalhas (Medalhas)
        $medalsSorted = $userData;
        usort($medalsSorted, function ($a, $b) {
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

        $this->cachedLikesRanking = [];
        foreach ($likesSorted as $index => $item) {
            $this->cachedLikesRanking[$item['id']] = $index + 1;
        }

        $this->cachedMedalsRanking = [];
        foreach ($medalsSorted as $index => $item) {
            $this->cachedMedalsRanking[$item['id']] = $index + 1;
        }

        return [
            'likes' => $this->cachedLikesRanking[$user->getId()] ?? 999,
            'medals' => $this->cachedMedalsRanking[$user->getId()] ?? 999
        ];
    }

    /**
     * Calcula e agrupa as estatísticas e medalhas de um treinador
     */
    public function getTrainerProfileData(User $user, bool $includeRanking = true): array
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

        // Pre-fetch detalhes do Pokémons para otimizar a performance (evita chamadas sequenciais à API)
        $caughtPokemon = $user->getCaughtPokemon();
        $caughtNames = [];
        foreach ($caughtPokemon as $key => $val) {
            $caughtNames[] = is_int($key) ? $val : $key;
        }
        $pokemonNamesToFetch = array_unique(array_merge(
            array_keys($uniquePokemonNames),
            $caughtNames
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

        foreach ($caughtPokemon as $key => $val) {
            $caughtName = is_int($key) ? $val : $key;
            $caughtDate = is_int($key) ? null : $val;
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
                    'types' => $types,
                    'caughtAt' => $caughtDate
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
            $status = $this->getMedalStatus($medal, $currentVal, $enabledMedals, $titles, $user, $includeRanking);

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
        $this->initializeDatabaseAndAvatars();

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

        $ranks = $this->getUserRankingPositions($user);

        $connection = $this->entityManager->getConnection();
        $avatars = $connection->fetchAllAssociative("SELECT * FROM avatar WHERE type = 'trainer' ORDER BY filename ASC");

        $avatarStatuses = [];
        $selectedAvatar = $user->getAvatar();
        if (empty($selectedAvatar)) {
            $selectedAvatar = 'trainer:unknown.png';
        }

        foreach ($avatars as $avatar) {
            $isLocked = false;
            $requirement = $avatar['requirement'];

            if (!$avatar['is_default']) {
                if ($avatar['req_rank_type'] !== null) {
                    $reqType = $avatar['req_rank_type'];
                    $reqPos = $avatar['req_rank_pos'] ?? 3;
                    $userPos = $ranks[$reqType] ?? 999;
                    if ($userPos > $reqPos) {
                        $isLocked = true;
                    }
                } elseif ($avatar['req_gold_count'] !== null) {
                    if ($goldCount < $avatar['req_gold_count']) {
                        $isLocked = true;
                    }
                } elseif ($avatar['req_medal'] !== null) {
                    $reqMedal = $avatar['req_medal'];
                    $reqTier = $avatar['req_tier'] ?? 'bronze';
                    $userTier = $medalsByName[$reqMedal] ?? 'locked';

                    $tierWeights = ['locked' => 0, 'bronze' => 1, 'silver' => 2, 'gold' => 3];
                    $userWeight = $tierWeights[$userTier] ?? 0;
                    $reqWeight = $tierWeights[$reqTier] ?? 3;

                    if ($userWeight < $reqWeight) {
                        $isLocked = true;
                    }
                } else {
                    $isLocked = true;
                }
            }

            $filename = $avatar['filename'];
            $shortName = substr($filename, 8); // remove 'trainer:'
            
            $isSelected = ($selectedAvatar === $filename) 
                || ($selectedAvatar === $shortName) 
                || ($selectedAvatar === 'unknown' && $filename === 'trainer:unknown.png');

            $avatarStatuses[] = [
                'filename' => $filename,
                'name' => str_replace(['.png', '-', '_'], ['', ' ', ' '], $shortName),
                'isLocked' => $isLocked,
                'requirement' => $requirement,
                'isSelected' => $isSelected,
                'reqMedal' => $avatar['req_medal'],
                'reqGoldCount' => $avatar['req_gold_count'],
                'reqRankType' => $avatar['req_rank_type'],
                'reqRankPos' => $avatar['req_rank_pos']
            ];
        }

        usort($avatarStatuses, function ($a, $b) {
            if ($a['isLocked'] !== $b['isLocked']) {
                return $a['isLocked'] <=> $b['isLocked'];
            }
            return strcmp($a['filename'], $b['filename']);
        });

        return $avatarStatuses;
    }

    /**
     * Auxiliar para mapear recompensas de medalhas (usando títulos da BD e avatares do código)
     */
    public function getMedalRewards(string $medalName, array $titles): array
    {
        $rewards = [];

        // Check DB avatars
        $this->initializeDatabaseAndAvatars();
        $connection = $this->entityManager->getConnection();
        $dbAvatars = $connection->fetchAllAssociative("SELECT * FROM avatar WHERE req_medal = ?", [$medalName]);
        foreach ($dbAvatars as $avatar) {
            $tier = $avatar['req_tier'] ?? 'gold';
            $filename = $avatar['filename'];
            $shortName = str_starts_with($filename, 'trainer:') ? substr($filename, 8) : (str_starts_with($filename, 'pkm:') ? substr($filename, 4) : $filename);
            $name = str_replace(['.png', '-', '_'], ['', ' ', ' '], $shortName);
            $rewards[$tier][] = "Avatar: " . ucwords($name);
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

        $ranks = $this->getUserRankingPositions($user);

        $titles = $this->titleRepository->findAll();
        $titleStatuses = [];
        $selectedTitle = $user->getTitle();

        foreach ($titles as $title) {
            $isLocked = false;

            if (!$title->isDefault()) {
                if ($title->getReqRankType() !== null) {
                    $reqType = $title->getReqRankType();
                    $reqPos = $title->getReqRankPos() ?? 3;
                    $userPos = $ranks[$reqType] ?? 999;
                    if ($userPos !== $reqPos) {
                        $isLocked = true;
                    }
                } elseif ($title->getReqGoldCount() !== null) {
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
            $ribbonUrl = $this->getRibbonUrl($ribbon);

            $titleStatuses[] = [
                'id' => $title->getId(),
                'name' => $title->getName(),
                'ribbon' => $ribbon,
                'ribbonUrl' => $ribbonUrl,
                'isLocked' => $isLocked,
                'requirement' => $title->getRequirement(),
                'isSelected' => ($selectedTitle === $title->getName()) || ($selectedTitle === null && $title->isDefault()),
                'reqMedal' => $title->getReqMedal(),
                'reqGoldCount' => $title->getReqGoldCount(),
                'reqRankType' => $title->getReqRankType(),
                'reqRankPos' => $title->getReqRankPos()
            ];
        }

        usort($titleStatuses, function ($a, $b) {
            if ($a['isLocked'] !== $b['isLocked']) {
                return $a['isLocked'] <=> $b['isLocked'];
            }
            return strcmp($a['name'], $b['name']);
        });

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
        ?User $user = null,
        bool $includeRanking = true
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

        // Se a lista de medalhas ativas não está vazia e esta medalha não está nela (para medalhas não-regionais), ou se está bloqueada regionalmente → force locked
        $adminLocked = $regionalLocked || (!$isRegionalMedal && !empty($enabledMedals) && !in_array($name, $enabledMedals));

        if ($adminLocked) {
            $tier        = 'locked';
            $nextTarget  = $bronze;
            $percent     = 0;
            if ($regionalLocked) {
                $description = 'Esta medalha está bloqueada pois sua região de registro é ' . ucfirst($user->getRegional()) . '.';
            } else {
                $description = 'Esta medalha está atualmente bloqueada.';
            }
        } elseif (($name === 'acclaimed' || $name === 'popular') && $includeRanking) {
            // Overrides para (Curtidas) e Popular (Medalhas) baseados nas colocações de ranking
            $ranks = $user ? $this->getUserRankingPositions($user) : ['likes' => 999, 'medals' => 999];
            $pos = ($name === 'acclaimed') ? $ranks['likes'] : $ranks['medals'];
            
            if ($pos === 1) {
                $tier = 'gold';
                $nextTarget = null;
                $percent = 100;
                $description = 'Você é o 1º colocado no ranking de ' . ($name === 'acclaimed' ? 'curtidas' : 'medalhas') . '!';
            } elseif ($pos === 2) {
                $tier = 'silver';
                $nextTarget = 1;
                $percent = 100;
                $description = 'Você é o 2º colocado no ranking de ' . ($name === 'acclaimed' ? 'curtidas' : 'medalhas') . '!';
            } elseif ($pos === 3) {
                $tier = 'bronze';
                $nextTarget = 2;
                $percent = 100;
                $description = 'Você é o 3º colocado no ranking de ' . ($name === 'acclaimed' ? 'curtidas' : 'medalhas') . '!';
            } else {
                $tier = 'locked';
                $nextTarget = 3;
                $percent = 0;
                $description = 'Disponível apenas para o Top 3 no ranking de ' . ($name === 'acclaimed' ? 'curtidas' : 'medalhas') . ' (Sua posição atual: ' . $pos . 'º).';
            }
        } elseif (($name === 'acclaimed' || $name === 'popular') && !$includeRanking) {
            // Se includeRanking é falso, tranca temporariamente para evitar recursão no cálculo do ranking base
            $tier = 'locked';
            $nextTarget = 3;
            $percent = 0;
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

    public function getTemplatesUnlockStatus(User $user, array $computedMedalGroups): array
    {
        $this->initializeDatabaseAndCardTemplates();

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

        $ranks = $this->getUserRankingPositions($user);

        $templates = $this->cardTemplateRepository->findAll();
        $templateStatuses = [];
        $selectedTemplate = $user->getCardTemplate();

        foreach ($templates as $template) {
            $isLocked = false;

            if (!$template->isDefault()) {
                if ($template->getReqRankType() !== null) {
                    $reqType = $template->getReqRankType();
                    $reqPos = $template->getReqRankPos() ?? 3;
                    $userPos = $ranks[$reqType] ?? 999;
                    if ($userPos > $reqPos) {
                        $isLocked = true;
                    }
                } elseif ($template->getReqGoldCount() !== null) {
                    if ($goldCount < $template->getReqGoldCount()) {
                        $isLocked = true;
                    }
                } elseif ($template->getReqMedal() !== null) {
                    $reqMedal = $template->getReqMedal();
                    $reqTier = $template->getReqTier() ?? 'bronze';
                    $userTier = $medalsByName[$reqMedal] ?? 'locked';

                    $tierWeights = ['locked' => 0, 'bronze' => 1, 'silver' => 2, 'gold' => 3];
                    $userWeight = $tierWeights[$userTier] ?? 0;
                    $reqWeight = $tierWeights[$reqTier] ?? 1;

                    if ($userWeight < $reqWeight) {
                        $isLocked = true;
                    }
                } else {
                    $isLocked = true;
                }
            }

            $imageUrl = null;
            if ($template->getImage()) {
                if (str_starts_with($template->getImage(), 'https://') || str_starts_with($template->getImage(), 'https://')) {
                    $imageUrl = $template->getImage();
                } else {
                    $imageUrl = 'https://raw.githubusercontent.com/thiago9852/pokemon-sprite/main/sprites/src/templates/' . $template->getImage();
                }
            }

            $templateStatuses[] = [
                'id' => $template->getId(),
                'name' => $template->getName(),
                'image' => $template->getImage(),
                'imageUrl' => $imageUrl,
                'isLocked' => $isLocked,
                'requirement' => $template->getRequirement(),
                'isSelected' => $selectedTemplate !== null && $selectedTemplate !== '' && $selectedTemplate === $template->getImage(),
                'reqMedal' => $template->getReqMedal(),
                'reqGoldCount' => $template->getReqGoldCount(),
                'reqRankType' => $template->getReqRankType(),
                'reqRankPos' => $template->getReqRankPos()
            ];
        }

        usort($templateStatuses, function ($a, $b) {
            if ($a['isLocked'] !== $b['isLocked']) {
                return $a['isLocked'] <=> $b['isLocked'];
            }
            return strcmp($a['name'], $b['name']);
        });

        return $templateStatuses;
    }

    public function initializeDatabaseAndCardTemplates(): void
    {
        if ($this->templatesInitialized) {
            return;
        }

        $this->templatesInitialized = true;
    }
    public function getAvatarUrl(?string $avatar): string
    {
        if (empty($avatar) || strcasecmp($avatar, 'unknown') === 0 || strcasecmp($avatar, 'trainer:unknown.png') === 0 || strcasecmp($avatar, 'unknown.png') === 0) {
            return 'https://raw.githubusercontent.com/thiago9852/pokemon-sprite/main/sprites/src/avatar/trainer/Unknown.png';
        }

        if (str_starts_with($avatar, 'pkm:')) {
            $filename = substr($avatar, 4);
            return 'https://raw.githubusercontent.com/thiago9852/pokemon-sprite/main/sprites/src/avatar/pkm/' . $filename;
        }

        $filename = str_starts_with($avatar, 'trainer:') ? substr($avatar, 8) : $avatar;
        return 'https://raw.githubusercontent.com/thiago9852/pokemon-sprite/main/sprites/src/avatar/trainer/' . $filename;
    }

    public function getPkmAvatarStatuses(User $user, array $computedMedalGroups = []): array
    {
        $this->initializeDatabaseAndAvatars();

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

        $connection = $this->entityManager->getConnection();
        $avatars = $connection->fetchAllAssociative("SELECT * FROM avatar WHERE type = 'pkm' ORDER BY filename ASC");

        $avatarStatuses = [];
        $selectedAvatar = $user->getAvatar();

        foreach ($avatars as $avatar) {
            $isLocked = false;
            $requirement = $avatar['requirement'];

            if (!$avatar['is_default']) {
                if ($avatar['req_gold_count'] !== null) {
                    if ($goldCount < $avatar['req_gold_count']) {
                        $isLocked = true;
                    }
                } elseif ($avatar['req_medal'] !== null) {
                    $reqMedal = $avatar['req_medal'];
                    $reqTier = $avatar['req_tier'] ?? 'bronze';
                    $userTier = $medalsByName[$reqMedal] ?? 'locked';

                    $tierWeights = ['locked' => 0, 'bronze' => 1, 'silver' => 2, 'gold' => 3];
                    $userWeight = $tierWeights[$userTier] ?? 0;
                    $reqWeight = $tierWeights[$reqTier] ?? 3;

                    if ($userWeight < $reqWeight) {
                        $isLocked = true;
                    }
                } else {
                    $isLocked = true;
                }
            }

            $filename = $avatar['filename'];
            $shortName = substr($filename, 4); // remove 'pkm:'

            $isSelected = ($selectedAvatar === $filename);

            $avatarStatuses[] = [
                'filename' => $filename,
                'name' => str_replace(['.png', '-', '_'], ['', ' ', ' '], $shortName),
                'isLocked' => $isLocked,
                'requirement' => $requirement,
                'isSelected' => $isSelected,
                'reqMedal' => $avatar['req_medal'],
                'reqGoldCount' => $avatar['req_gold_count']
            ];
        }

        usort($avatarStatuses, function ($a, $b) {
            if ($a['isLocked'] !== $b['isLocked']) {
                return $a['isLocked'] <=> $b['isLocked'];
            }
            return strcmp($a['filename'], $b['filename']);
        });
        
        return $avatarStatuses;
    }

    public function initializeDatabaseAndAvatars(): void
    {
        if ($this->avatarsInitialized) {
            return;
        }

        try {
            $count = $this->entityManager->getRepository(\App\Entity\Avatar::class)->count([]);
            if ($count === 0) {
                $connection = $this->entityManager->getConnection();
                
                $connection->insert('avatar', [
                    'filename' => 'trainer:unknown.png',
                    'type' => 'trainer',
                    'requirement' => 'Padrão do Sistema',
                    'req_medal' => null,
                    'req_tier' => null,
                    'req_gold_count' => null,
                    'req_rank_type' => null,
                    'req_rank_pos' => null,
                    'is_default' => 1
                ]);

                foreach (['Ash.png', 'Beauty.png', 'Hiker.png'] as $defTrainer) {
                    $connection->insert('avatar', [
                        'filename' => 'trainer:' . $defTrainer,
                        'type' => 'trainer',
                        'requirement' => 'Padrão do Sistema',
                        'req_medal' => null,
                        'req_tier' => null,
                        'req_gold_count' => null,
                        'req_rank_type' => null,
                        'req_rank_pos' => null,
                        'is_default' => 1
                    ]);
                }

                foreach (AvatarConfig::AVATAR_REWARDS as $trainer => $req) {
                    $connection->insert('avatar', [
                        'filename' => 'trainer:' . $trainer,
                        'type' => 'trainer',
                        'requirement' => $req['label'],
                        'req_medal' => $req['medal'],
                        'req_tier' => $req['tier'],
                        'req_gold_count' => null,
                        'req_rank_type' => null,
                        'req_rank_pos' => null,
                        'is_default' => 0
                    ]);
                }

                foreach (AvatarConfig::PKM_AVATARS as $pkm) {
                    $isPkmDefault = in_array(strtolower($pkm), ['charizard.png', 'lucario.png']) ? 1 : 0;
                    $connection->insert('avatar', [
                        'filename' => 'pkm:' . $pkm,
                        'type' => 'pkm',
                        'requirement' => $isPkmDefault ? 'Padrão do Sistema' : 'Bloqueado por padrão',
                        'req_medal' => null,
                        'req_tier' => null,
                        'req_gold_count' => null,
                        'req_rank_type' => null,
                        'req_rank_pos' => null,
                        'is_default' => $isPkmDefault
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Ignore
        }

        $this->avatarsInitialized = true;
    }

    public function getRibbonUrl(?string $ribbon): ?string
    {
        if (!$ribbon) {
            return null;
        }
        if (str_starts_with($ribbon, 'http://') || str_starts_with($ribbon, 'https://')) {
            return $ribbon;
        }
        return 'https://raw.githubusercontent.com/msikma/pokesprite/master/misc/ribbon/gen8/' . $ribbon;
    }

    public function syncAvatarsFromApi(): array
    {
        $this->initializeDatabaseAndAvatars();
        $connection = $this->entityManager->getConnection();
        $inserted = 0;
        $total = 0;

        try {
            // 1. Sincronizar avatares de Treinador
            $response = $this->httpClient->request('GET', 'https://api.github.com/repos/thiago9852/pokemon-sprite/contents/sprites/src/avatar/trainer', [
                'headers' => [
                    'User-Agent' => 'PokeFlaton-App'
                ]
            ]);
            
            if ($response->getStatusCode() === 200) {
                $files = $response->toArray();
                foreach ($files as $file) {
                    if (isset($file['type']) && $file['type'] === 'file' && str_ends_with(strtolower($file['name']), '.png')) {
                        $filename = 'trainer:' . $file['name'];
                        
                        // Verificação case-insensitive no banco de dados para evitar duplicados como unknown.png vs Unknown.png
                        $exists = $connection->fetchOne("SELECT COUNT(*) FROM avatar WHERE LOWER(filename) = LOWER(?)", [$filename]);
                        if (!$exists) {
                            $isDefault = 0;
                            $reqMedal = null;
                            $reqTier = null;
                            $requirement = 'Bloqueado por padrão';

                            // Busca correspondência nas recompensas de forma case-insensitive
                            $matchedReq = null;
                            foreach (AvatarConfig::AVATAR_REWARDS as $rewardFile => $rewardData) {
                                if (strcasecmp($rewardFile, $file['name']) === 0) {
                                    $matchedReq = $rewardData;
                                    break;
                                }
                            }

                            if ($matchedReq !== null) {
                                $isDefault = 0;
                                $reqMedal = $matchedReq['medal'];
                                $reqTier = $matchedReq['tier'];
                                $requirement = $matchedReq['label'];
                            } elseif (in_array(strtolower($file['name']), ['ash.png', 'beauty.png', 'hiker.png', 'Unknown.png'])) {
                                $isDefault = 1;
                                $requirement = 'Padrão do Sistema';
                            }

                            $connection->insert('avatar', [
                                'filename' => $filename,
                                'type' => 'trainer',
                                'requirement' => $requirement,
                                'req_medal' => $reqMedal,
                                'req_tier' => $reqTier,
                                'req_gold_count' => null,
                                'is_default' => $isDefault
                            ]);
                            $inserted++;
                        }
                        $total++;
                    }
                }
            }
        } catch (\Exception $e) {
            // ignore
        }

        try {
            // 2. Sincronizar avatares Pokémon
            $response = $this->httpClient->request('GET', 'https://api.github.com/repos/thiago9852/pokemon-sprite/contents/sprites/src/avatar/pkm', [
                'headers' => [
                    'User-Agent' => 'PokeFlaton-App'
                ]
            ]);
            
            if ($response->getStatusCode() === 200) {
                $files = $response->toArray();
                foreach ($files as $file) {
                    if (isset($file['type']) && $file['type'] === 'file' && str_ends_with(strtolower($file['name']), '.png')) {
                        $filename = 'pkm:' . $file['name'];
                        
                        // Verificação case-insensitive no banco de dados
                        $exists = $connection->fetchOne("SELECT COUNT(*) FROM avatar WHERE LOWER(filename) = LOWER(?)", [$filename]);
                        if (!$exists) {
                            $isPkmDefault = in_array(strtolower($file['name']), ['charizard.png', 'lucario.png']) ? 1 : 0;
                            $connection->insert('avatar', [
                                'filename' => $filename,
                                'type' => 'pkm',
                                'requirement' => $isPkmDefault ? 'Padrão do Sistema' : 'Bloqueado por padrão',
                                'req_medal' => null,
                                'req_tier' => null,
                                'req_gold_count' => null,
                                'is_default' => $isPkmDefault
                            ]);
                            $inserted++;
                        }
                        $total++;
                    }
                }
            }
        } catch (\Exception $e) {
            // ignore
        }

        return [
            'inserted' => $inserted,
            'total' => $total
        ];
    }

    public function resetAndSyncAvatars(): array
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement("DELETE FROM avatar");
        
        return $this->syncAvatarsFromApi();
    }

    public function syncTemplatesFromApi(): array
    {
        $this->initializeDatabaseAndCardTemplates();
        $connection = $this->entityManager->getConnection();
        $inserted = 0;
        $total = 0;

        try {
            $response = $this->httpClient->request('GET', 'https://api.github.com/repos/thiago9852/pokemon-sprite/contents/sprites/src/templates', [
                'headers' => [
                    'User-Agent' => 'PokeFlaton-App'
                ]
            ]);
            
            if ($response->getStatusCode() === 200) {
                $files = $response->toArray();
                foreach ($files as $file) {
                    if (isset($file['type']) && $file['type'] === 'file' && str_ends_with(strtolower($file['name']), '.png')) {
                        $filename = $file['name'];
                        
                        // Verificação case-insensitive no banco de dados para evitar duplicados
                        $exists = $connection->fetchOne("SELECT COUNT(*) FROM card_template WHERE LOWER(image) = LOWER(?)", [$filename]);
                        if (!$exists) {
                            $name = str_replace(['.png', '-', '_'], ['', ' ', ' '], $filename);
                            $name = ucwords($name);

                            $connection->insert('card_template', [
                                'name' => $name,
                                'image' => $filename,
                                'requirement' => 'Bloqueado por padrão',
                                'req_medal' => null,
                                'req_tier' => null,
                                'req_gold_count' => null,
                                'req_rank_type' => null,
                                'req_rank_pos' => null,
                                'is_default' => 0
                            ]);
                            $inserted++;
                        }
                        $total++;
                    }
                }
            }
        } catch (\Exception $e) {
            // ignore
        }

        return [
            'inserted' => $inserted,
            'total' => $total
        ];
    }

    public function resetAndSyncTemplates(): array
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement("DELETE FROM card_template");
        
        return $this->syncTemplatesFromApi();
    }
}
