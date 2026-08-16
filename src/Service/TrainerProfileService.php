<?php

namespace App\Service;

use App\Config\AvatarConfig;
use App\Entity\Avatar;
use App\Entity\User;
use App\Repository\CardTemplateRepository;
use App\Repository\MovesetRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class TrainerProfileService
{
    private bool $avatarsInitialized = false;
    private bool $templatesInitialized = false;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PokeApiService $pokeApiService,
        private readonly MovesetRepository $movesetRepository,
        private readonly UserRepository $userRepository,
        private readonly CardTemplateRepository $cardTemplateRepository,
        private readonly HttpClientInterface $httpClient,
        private readonly TranslatorInterface $translator,
    ) {
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
        } catch (\Exception) {
            // Ignore
        }
    }

    /**
     * Calcula e agrupa as estatísticas de um treinador.
     */
    public function getTrainerProfileData(User $user): array
    {
        $this->initializeMovesetColumns();

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
        if ($pokemonNamesToFetch !== []) {
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
            } catch (\Exception) {
                // ignore
            }
        }
        $typesCreatedCount = count($uniqueTypesCreated);
        $tmsCount = count($user->getUnlockedTms());

        // 3. Pokémons Capturados e estatísticas detalhadas
        $caughtCount = count($caughtPokemon);
        $caughtDetails = [];

        foreach ($caughtPokemon as $key => $val) {
            $caughtName = is_int($key) ? $val : $key;
            $caughtDate = is_int($key) ? null : $val;
            try {
                $details = $fetchedDetails[strtolower($caughtName)] ?? $this->pokeApiService->getPokemonDetails($caughtName);

                $caughtDetails[] = [
                    'name' => $details['name'],
                    'id' => $details['id'],
                    'sprite' => $details['sprite_official'],
                    'types' => $details['types'],
                    'caughtAt' => $caughtDate,
                ];
            } catch (\Exception) {
                // ignore
            }
        }

        $followingCount = count($user->getFollowing());

        // Contar seguidores
        $qb = $this->userRepository->createQueryBuilder('u');
        $followersCount = (int) $qb->select('count(u.id)')
            ->where('u.following LIKE :username')
            ->setParameter('username', '%"'.$user->getUsername().'"%')
            ->getQuery()
            ->getSingleScalarResult();

        $followersList = $this->userRepository->createQueryBuilder('u')
            ->where('u.following LIKE :username')
            ->setParameter('username', '%"'.$user->getUsername().'"%')
            ->getQuery()
            ->getResult();

        return [
            'createdCount' => $createdCount,
            'totalVotes' => $totalVotes,
            'typesCount' => $typesCreatedCount,
            'tmsCount' => $tmsCount,
            'caughtCount' => $caughtCount,
            'followingCount' => $followingCount,
            'followersCount' => $followersCount,
            'caughtDetails' => $caughtDetails,
            'userMovesets' => $userMovesets,
            'followersList' => $followersList,
        ];
    }

    /**
     * Lista os avatares de treinador disponíveis para seleção.
     */
    public function getAvatarUnlockStatus(User $user): array
    {
        $this->initializeDatabaseAndAvatars();

        $connection = $this->entityManager->getConnection();
        $avatars = $connection->fetchAllAssociative("SELECT * FROM avatar WHERE type = 'trainer' ORDER BY filename ASC");

        $avatarStatuses = [];
        $selectedAvatar = $user->getAvatar();
        if (empty($selectedAvatar)) {
            $selectedAvatar = 'trainer:unknown.png';
        }

        foreach ($avatars as $avatar) {
            $filename = $avatar['filename'];
            $shortName = substr($filename, 8); // remove 'trainer:'

            $isSelected = ($selectedAvatar === $filename)
                || ($selectedAvatar === $shortName)
                || ($selectedAvatar === 'unknown' && $filename === 'trainer:unknown.png');

            $avatarStatuses[] = [
                'filename' => $filename,
                'name' => $this->translator->trans(ucwords(str_replace(['.png', '-', '_'], ['', ' ', ' '], $shortName))),
                'isSelected' => $isSelected,
            ];
        }

        usort($avatarStatuses, fn ($a, $b) => strcmp($a['filename'], $b['filename']));

        return $avatarStatuses;
    }

    public function getTemplatesUnlockStatus(User $user): array
    {
        $this->initializeDatabaseAndCardTemplates();

        $templates = $this->cardTemplateRepository->findAll();
        $templateStatuses = [];
        $selectedTemplate = $user->getCardTemplate();

        foreach ($templates as $template) {
            $imageUrl = null;
            if ($template->getImage()) {
                if (str_starts_with($template->getImage(), 'https://')) {
                    $imageUrl = $template->getImage();
                } else {
                    $imageUrl = 'https://raw.githubusercontent.com/thiago9852/pokemon-sprite/main/sprites/src/templates/'.$template->getImage();
                }
            }

            $templateStatuses[] = [
                'id' => $template->getId(),
                'name' => $this->translator->trans($template->getName()),
                'image' => $template->getImage(),
                'imageUrl' => $imageUrl,
                'requirement' => $this->translator->trans($template->getRequirement()),
                'isSelected' => $selectedTemplate !== null && $selectedTemplate !== '' && $selectedTemplate === $template->getImage(),
            ];
        }

        usort($templateStatuses, fn ($a, $b) => strcmp($a['name'], $b['name']));

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

            return 'https://raw.githubusercontent.com/thiago9852/pokemon-sprite/main/sprites/src/avatar/pkm/'.$filename;
        }

        $filename = str_starts_with($avatar, 'trainer:') ? substr($avatar, 8) : $avatar;

        return 'https://raw.githubusercontent.com/thiago9852/pokemon-sprite/main/sprites/src/avatar/trainer/'.$filename;
    }

    /**
     * Lista os avatares de Pokémon disponíveis para seleção.
     */
    public function getPkmAvatarStatuses(User $user): array
    {
        $this->initializeDatabaseAndAvatars();

        $connection = $this->entityManager->getConnection();
        $avatars = $connection->fetchAllAssociative("SELECT * FROM avatar WHERE type = 'pkm' ORDER BY filename ASC");

        $avatarStatuses = [];
        $selectedAvatar = $user->getAvatar();

        foreach ($avatars as $avatar) {
            $filename = $avatar['filename'];
            $shortName = substr($filename, 4); // remove 'pkm:'

            $avatarStatuses[] = [
                'filename' => $filename,
                'name' => $this->translator->trans(ucwords(str_replace(['.png', '-', '_'], ['', ' ', ' '], $shortName))),
                'isSelected' => $selectedAvatar === $filename,
            ];
        }

        usort($avatarStatuses, fn ($a, $b) => strcmp($a['filename'], $b['filename']));

        return $avatarStatuses;
    }

    public function initializeDatabaseAndAvatars(): void
    {
        if ($this->avatarsInitialized) {
            return;
        }

        try {
            $count = $this->entityManager->getRepository(Avatar::class)->count([]);
            if ($count === 0) {
                $connection = $this->entityManager->getConnection();

                $connection->insert('avatar', [
                    'filename' => 'trainer:unknown.png',
                    'type' => 'trainer',
                    'requirement' => 'Padrão do Sistema',
                    'is_default' => 1,
                ]);

                foreach (['Ash.png', 'Beauty.png', 'Hiker.png'] as $defTrainer) {
                    $connection->insert('avatar', [
                        'filename' => 'trainer:'.$defTrainer,
                        'type' => 'trainer',
                        'requirement' => 'Padrão do Sistema',
                        'is_default' => 1,
                    ]);
                }

                foreach (AvatarConfig::PKM_AVATARS as $pkm) {
                    $isPkmDefault = in_array(strtolower($pkm), ['charizard.png', 'lucario.png']) ? 1 : 0;
                    $connection->insert('avatar', [
                        'filename' => 'pkm:'.$pkm,
                        'type' => 'pkm',
                        'requirement' => 'Disponível',
                        'is_default' => $isPkmDefault,
                    ]);
                }
            }
        } catch (\Exception) {
            // Ignore
        }

        $this->avatarsInitialized = true;
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
                    'User-Agent' => 'MovSet-App',
                ],
            ]);

            if ($response->getStatusCode() === 200) {
                $files = $response->toArray();
                foreach ($files as $file) {
                    if (isset($file['type']) && $file['type'] === 'file' && str_ends_with(strtolower($file['name']), '.png')) {
                        $filename = 'trainer:'.$file['name'];

                        // Verificação case-insensitive no banco de dados para evitar duplicados como unknown.png vs Unknown.png
                        $exists = $connection->fetchOne('SELECT COUNT(*) FROM avatar WHERE LOWER(filename) = LOWER(?)', [$filename]);
                        if (!$exists) {
                            $isDefault = in_array(strtolower($file['name']), ['ash.png', 'beauty.png', 'hiker.png', 'unknown.png']) ? 1 : 0;

                            $connection->insert('avatar', [
                                'filename' => $filename,
                                'type' => 'trainer',
                                'requirement' => $isDefault ? 'Padrão do Sistema' : 'Disponível',
                                'is_default' => $isDefault,
                            ]);
                            ++$inserted;
                        }
                        ++$total;
                    }
                }
            }
        } catch (\Exception) {
            // ignore
        }

        try {
            // 2. Sincronizar avatares Pokémon
            $response = $this->httpClient->request('GET', 'https://api.github.com/repos/thiago9852/pokemon-sprite/contents/sprites/src/avatar/pkm', [
                'headers' => [
                    'User-Agent' => 'MovSet-App',
                ],
            ]);

            if ($response->getStatusCode() === 200) {
                $files = $response->toArray();
                foreach ($files as $file) {
                    if (isset($file['type']) && $file['type'] === 'file' && str_ends_with(strtolower($file['name']), '.png')) {
                        $filename = 'pkm:'.$file['name'];

                        // Verificação case-insensitive no banco de dados
                        $exists = $connection->fetchOne('SELECT COUNT(*) FROM avatar WHERE LOWER(filename) = LOWER(?)', [$filename]);
                        if (!$exists) {
                            $isPkmDefault = in_array(strtolower($file['name']), ['charizard.png', 'lucario.png']) ? 1 : 0;
                            $connection->insert('avatar', [
                                'filename' => $filename,
                                'type' => 'pkm',
                                'requirement' => $isPkmDefault ? 'Padrão do Sistema' : 'Disponível',
                                'is_default' => $isPkmDefault,
                            ]);
                            ++$inserted;
                        }
                        ++$total;
                    }
                }
            }
        } catch (\Exception) {
            // ignore
        }

        return [
            'inserted' => $inserted,
            'total' => $total,
        ];
    }

    public function resetAndSyncAvatars(): array
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('DELETE FROM avatar');

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
                    'User-Agent' => 'MovSet-App',
                ],
            ]);

            if ($response->getStatusCode() === 200) {
                $files = $response->toArray();
                foreach ($files as $file) {
                    if (isset($file['type']) && $file['type'] === 'file' && str_ends_with(strtolower($file['name']), '.png')) {
                        $filename = $file['name'];

                        // Verificação case-insensitive no banco de dados para evitar duplicados
                        $exists = $connection->fetchOne('SELECT COUNT(*) FROM card_template WHERE LOWER(image) = LOWER(?)', [$filename]);
                        if (!$exists) {
                            $name = str_replace(['.png', '-', '_'], ['', ' ', ' '], $filename);
                            $name = ucwords($name);

                            $connection->insert('card_template', [
                                'name' => $name,
                                'image' => $filename,
                                'requirement' => 'Disponível',
                                'is_default' => 0,
                            ]);
                            ++$inserted;
                        }
                        ++$total;
                    }
                }
            }
        } catch (\Exception) {
            // ignore
        }

        return [
            'inserted' => $inserted,
            'total' => $total,
        ];
    }

    public function resetAndSyncTemplates(): array
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('DELETE FROM card_template');

        return $this->syncTemplatesFromApi();
    }
}
