<?php

namespace App\Controller;

use App\Repository\UserRepository;
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

    public function __construct(
        #[Autowire('%kernel.project_dir%')] string $projectDir,
        UserRepository $userRepository
    ) {
        $this->projectDir     = $projectDir;
        $this->userRepository = $userRepository;
    }

    // All medals with their display info for the admin panel
    private function getAllMedalDefinitions(): array
    {
        return [
            'Atividade e Comunidade' => [
                ['key' => 'creator',        'title' => 'Cientista',              'badge' => 'general/gold/scientist.webp'],
                ['key' => 'acclaimed',       'title' => 'Treinador Aclamado',     'badge' => 'general/gold/rising-star.webp'],
                ['key' => 'collector',       'title' => 'Colecionador de TMs',    'badge' => 'general/gold/collector.webp'],
                ['key' => 'friendship',      'title' => 'Laço de Amizade',        'badge' => 'general/gold/friend-finder.webp'],
                ['key' => 'popular',         'title' => 'Estrela da Comunidade',  'badge' => 'general/gold/idol.webp'],
                ['key' => 'gotta-catch-all', 'title' => 'Gotta Catch Em All',     'badge' => 'general/gold/pokemon-ranger.webp'],
            ],
            'Enciclopédia Pokémon' => [
                ['key' => 'pokedex',   'title' => 'Pesquisador Pokémon', 'badge' => 'general/gold/ace-trainer.webp'],
                ['key' => 'fisherman', 'title' => 'Pescador',            'badge' => 'general/gold/fisher.webp'],
                ['key' => 'vivillon',  'title' => 'Coleção Vivillon',    'badge' => 'general/gold/vivillon-collector.webp'],
                ['key' => 'pikachu',   'title' => 'Fã de Pikachu',       'badge' => 'general/gold/pikachu-fan.webp'],
                ['key' => 'youngster', 'title' => 'Estilo Jovem',        'badge' => 'general/gold/gentleman.webp'],
            ],
            'Pokédex Regional' => [
                ['key' => 'kanto',  'title' => 'Kanto',      'badge' => 'general/gold/kanto.webp'],
                ['key' => 'johto',  'title' => 'Johto',      'badge' => 'general/gold/johto.webp'],
                ['key' => 'hoenn',  'title' => 'Hoenn',      'badge' => 'general/gold/hoenn.webp'],
                ['key' => 'sinnoh', 'title' => 'Sinnoh',     'badge' => 'general/gold/sinnoh.webp'],
                ['key' => 'unova',  'title' => 'Unova',      'badge' => 'general/gold/unova.webp'],
                ['key' => 'kalos',  'title' => 'Kalos',      'badge' => 'general/gold/kalos.webp'],
                ['key' => 'alola',  'title' => 'Alola',      'badge' => 'general/gold/alola.webp'],
                ['key' => 'galar',  'title' => 'Galar/Hisui','badge' => 'general/gold/galar.webp'],
                ['key' => 'paldea', 'title' => 'Paldea',     'badge' => 'general/gold/paldea.webp'],
            ],
            'Captura por Tipo' => [
                ['key' => 'type_normal',   'title' => 'Estudante',           'badge' => 'type/gold/schoolkid.webp'],
                ['key' => 'type_fire',     'title' => 'Esquentado',          'badge' => 'type/gold/kindler.webp'],
                ['key' => 'type_water',    'title' => 'Nadador',             'badge' => 'type/gold/swimmer.webp'],
                ['key' => 'type_grass',    'title' => 'Jardineiro',          'badge' => 'type/gold/gardener.webp'],
                ['key' => 'type_electric', 'title' => 'Roqueiro',            'badge' => 'type/gold/rocker.webp'],
                ['key' => 'type_ice',      'title' => 'Esquiador',           'badge' => 'type/gold/skier.webp'],
                ['key' => 'type_fighting', 'title' => 'Cinturão Negro',      'badge' => 'type/gold/black-belt.webp'],
                ['key' => 'type_poison',   'title' => 'Garota Punk',         'badge' => 'type/gold/punk-girl.webp'],
                ['key' => 'type_ground',   'title' => 'Maníaco das Ruínas',  'badge' => 'type/gold/ruin-maniac.webp'],
                ['key' => 'type_flying',   'title' => 'Ornitólogo',          'badge' => 'type/gold/bird-keeper.webp'],
                ['key' => 'type_psychic',  'title' => 'Médium',              'badge' => 'type/gold/psychic.webp'],
                ['key' => 'type_bug',      'title' => 'Caçador de Insetos',  'badge' => 'type/gold/bug-catcher.webp'],
                ['key' => 'type_rock',     'title' => 'Montanhista',         'badge' => 'type/gold/hiker.webp'],
                ['key' => 'type_ghost',    'title' => 'Místico',             'badge' => 'type/gold/hex-maniac.webp'],
                ['key' => 'type_dragon',   'title' => 'Domador de Dragões',  'badge' => 'type/gold/dragon-tamer.webp'],
                ['key' => 'type_steel',    'title' => 'Agente do Pátio',     'badge' => 'type/gold/rail-staff.webp'],
                ['key' => 'type_dark',     'title' => 'Delinquente',         'badge' => 'type/gold/delinquent.webp'],
                ['key' => 'type_fairy',    'title' => 'Conto de Fadas',      'badge' => 'type/gold/fairy-tale-girl.webp'],
            ],
        ];
    }

    private function getAllVivillonPatterns(): array
    {
        return [
            'meadow', 'archipelago', 'continental', 'elegant', 'garden',
            'high-plains', 'icy-snow', 'jungle', 'marine', 'modern',
            'monsoon', 'ocean', 'polar', 'river', 'sandstorm',
            'savanna', 'sun', 'tundra', 'fancy', 'poke-ball'
        ];
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

        $config                = $this->loadConfig();
        $enabledMedals         = $config['enabled_medals'] ?? [];
        $enabledVivillonPats   = $config['enabled_vivillon_patterns'] ?? [];
        $medalDefs             = $this->getAllMedalDefinitions();
        $allVivillonPatterns   = $this->getAllVivillonPatterns();
        $baseUrl               = 'https://raw.githubusercontent.com/KovuTheHusky/pokemon-medals/main/';
        $totalUsers            = count($this->userRepository->findAll());

        // Count users with each medal progress
        $allUsers = $this->userRepository->findAll();

        return $this->render('admin/index.html.twig', [
            'medalDefs'           => $medalDefs,
            'enabledMedals'       => $enabledMedals,
            'enabledVivillonPats' => $enabledVivillonPats,
            'allVivillonPatterns' => $allVivillonPatterns,
            'badgeBaseUrl'        => $baseUrl,
            'totalUsers'          => $totalUsers,
        ]);
    }

    #[Route('/admin/medal/toggle', name: 'app_admin_medal_toggle', methods: ['POST'])]
    public function toggleMedal(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $medal   = $request->request->get('medal', '');
        $enabled = $request->request->get('enabled', 'false') === 'true';
        $type    = $request->request->get('type', 'medal'); // 'medal' or 'vivillon'

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

        $action = $request->request->get('action', ''); // 'enable_all' or 'disable_all'
        $config = $this->loadConfig();

        if ($action === 'enable_all') {
            // Enable all medals
            $allMedals = [];
            foreach ($this->getAllMedalDefinitions() as $medals) {
                foreach ($medals as $m) {
                    $allMedals[] = $m['key'];
                }
            }
            $config['enabled_medals'] = $allMedals;
            $config['enabled_vivillon_patterns'] = $this->getAllVivillonPatterns();
        } elseif ($action === 'disable_all') {
            $config['enabled_medals'] = [];
            $config['enabled_vivillon_patterns'] = [];
        }

        $this->saveConfig($config);
        return new JsonResponse(['success' => true, 'action' => $action]);
    }
}
