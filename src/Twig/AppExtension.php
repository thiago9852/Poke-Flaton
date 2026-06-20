<?php

namespace App\Twig;

use App\Repository\PokemonLocationRepository;
use App\Service\TrainerProfileService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Twig\TwigFilter;

class AppExtension extends AbstractExtension
{
    private PokemonLocationRepository $pokemonLocationRepository;
    private TrainerProfileService $trainerProfileService;

    public function __construct(
        PokemonLocationRepository $pokemonLocationRepository,
        TrainerProfileService $trainerProfileService
    ) {
        $this->pokemonLocationRepository = $pokemonLocationRepository;
        $this->trainerProfileService = $trainerProfileService;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('pending_locations_count', [$this, 'getPendingLocationsCount']),
            new TwigFunction('avatar_url', [$this, 'getAvatarUrl']),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('abbr_pokemon_name', [$this, 'abbreviatePokemonName']),
            new TwigFilter('abbr_variety_name', [$this, 'abbreviateVarietyName']),
        ];
    }

    public function getPendingLocationsCount(): int
    {
        return $this->pokemonLocationRepository->count(['isApproved' => false]);
    }

    public function getAvatarUrl(?string $avatar): string
    {
        return $this->trainerProfileService->getAvatarUrl($avatar);
    }

    public function abbreviateVarietyName(string $name, string $speciesName): string
    {
        $nameLower = strtolower(trim($name));
        $speciesLower = strtolower(trim($speciesName));

        // Remover o nome da espécie se ele for um prefixo no nome da variedade
        if (str_starts_with($nameLower, $speciesLower . '-')) {
            $suffix = substr($nameLower, strlen($speciesLower) + 1);
        } else {
            $suffix = $nameLower;
        }

        // Se após remover o nome da espécie ficar vazio, retorna "Padrão"
        if ($suffix === '') {
            return 'Padrão';
        }

        // Casos especiais diretos para o sufixo
        $specialSuffixes = [
            'paldea-combat-breed' => 'Combat',
            'paldea-blaze-breed'  => 'Blaze',
            'paldea-aqua-breed'   => 'Aqua',
            'single-strike'       => 'Single',
            'rapid-strike'        => 'Rapid',
            'ice-rider'           => 'Ice',
            'shadow-rider'        => 'Shadow',
            'galar-standard'      => 'Galar',
            'galar-zen'           => 'Zen',
            'crowned-sword'       => 'Crowned',
            'crowned-shield'      => 'Crowned',
            'red-striped'         => 'Red',
            'blue-striped'        => 'Blue',
            'white-striped'       => 'White',
            'gmax'                => 'G-Max',
            'mega-x'              => 'Mega X',
            'mega-y'              => 'Mega Y',
        ];

        if (isset($specialSuffixes[$suffix])) {
            return $specialSuffixes[$suffix];
        }

        // Regras genéricas para encurtar partes conhecidas
        $parts = explode('-', $suffix);

        $termMap = [
            'paldea' => 'P.',
            'alola' => 'Alola',
            'galar' => 'Galar',
            'hisui' => 'Hisui',
            'gmax' => 'G-Max',
            'mega' => 'Mega',
            'standard' => '',
            'combat' => 'Combat',
            'blaze' => 'Blaze',
            'aqua' => 'Aqua',
            'breed' => '',
            'single' => 'Single',
            'strike' => 'Strike',
            'rapid' => 'Rapid',
            'crowned' => 'Crowned',
            'origin' => 'Origin',
            'therian' => 'Therian',
            'incarnate' => 'Incarnate',
            'altered' => 'Altered',
            'zen' => 'Zen',
            'average' => 'Avg',
            'super' => 'Super',
            'small' => 'Small',
            'large' => 'Large',
            'dusk' => 'Dusk',
            'midnight' => 'Midnight',
            'midday' => 'Midday',
            'shadow' => 'Shadow',
            'ice' => 'Ice',
            'rider' => '',
        ];

        $newParts = [];
        foreach ($parts as $part) {
            if (isset($termMap[$part])) {
                if ($termMap[$part] !== '') {
                    $newParts[] = $termMap[$part];
                }
            } else {
                $newParts[] = ucfirst($part);
            }
        }

        if (empty($newParts)) {
            return 'Padrão';
        }

        return implode(' ', $newParts);
    }

    public function abbreviatePokemonName(string $name): string
    {
        $nameLower = strtolower(trim($name));

        // Substituições exatas e casos especiais
        $specialCases = [
            'ho-oh' => 'Ho-Oh',
            'porygon-z' => 'Porygon-Z',
            'jangmo-o' => 'Jangmo-o',
            'hakamo-o' => 'Hakamo-o',
            'kommo-o' => 'Kommo-o',
            'wo-chien' => 'Wo-Chien',
            'chien-pao' => 'Chien-Pao',
            'ting-lu' => 'Ting-Lu',
            'chi-yu' => 'Chi-Yu',
            'nidoran-f' => 'Nidoran (F)',
            'nidoran-m' => 'Nidoran (M)',
            'mr-mime' => 'Mr. Mime',
            'mime-jr' => 'Mime Jr.',
            'mr-rime' => 'Mr. Rime',
            'type-null' => 'Type: Null',
            'tapu-koko' => 'Tapu Koko',
            'tapu-lele' => 'Tapu Lele',
            'tapu-bulu' => 'Tapu Bulu',
            'tapu-fini' => 'Tapu Fini',
            'great-tusk' => 'Great Tusk',
            'scream-tail' => 'Scream Tail',
            'brute-bonnet' => 'Brute Bonnet',
            'flutter-mane' => 'Flutter Mane',
            'slither-wing' => 'Slither Wing',
            'sandy-shocks' => 'Sandy Shocks',
            'iron-treads' => 'Iron Treads',
            'iron-bundle' => 'Iron Bundle',
            'iron-hands' => 'Iron Hands',
            'iron-jugulis' => 'Iron Jugulis',
            'iron-moth' => 'Iron Moth',
            'iron-thorns' => 'Iron Thorns',
            'iron-valiant' => 'Iron Valiant',
            'roaring-moon' => 'Roaring Moon',
            'iron-leaves' => 'Iron Leaves',
            'walking-wake' => 'Walking Wake',
            'iron-crown' => 'Iron Crown',
            'iron-boulder' => 'Iron Boulder',
            'gouging-fire' => 'Gouging Fire',
            'raging-bolt' => 'Raging Bolt',

            // Sub-formas específicas muito grandes
            'tauros-paldea-combat-breed' => 'Tauros (Combat)',
            'tauros-paldea-blaze-breed'  => 'Tauros (Blaze)',
            'tauros-paldea-aqua-breed'   => 'Tauros (Aqua)',
            'urshifu-single-strike'      => 'Urshifu (Single)',
            'urshifu-rapid-strike'       => 'Urshifu (Rapid)',
            'calyrex-ice-rider'          => 'Calyrex (Ice)',
            'calyrex-shadow-rider'       => 'Calyrex (Shadow)',
            'darmanitan-galar-standard'  => 'Darmanitan (Galar)',
            'darmanitan-galar-zen'       => 'Darmanitan (G. Zen)',
            'zacian-crowned-sword'       => 'Zacian (Crowned)',
            'zamazenta-crowned-shield'   => 'Zamazenta (Crowned)',
            'basculin-red-striped'       => 'Basculin (Red)',
            'basculin-blue-striped'      => 'Basculin (Blue)',
            'basculin-white-striped'     => 'Basculin (White)',
            'lycanroc-midday'            => 'Lycanroc (Midday)',
            'lycanroc-midnight'          => 'Lycanroc (Midnight)',
            'lycanroc-dusk'              => 'Lycanroc (Dusk)',
            'rotom-mow'                  => 'Rotom (Mow)',
            'rotom-frost'                => 'Rotom (Frost)',
            'rotom-heat'                 => 'Rotom (Heat)',
            'rotom-wash'                 => 'Rotom (Wash)',
            'rotom-fan'                  => 'Rotom (Fan)',
            'charizard-gmax'             => 'Charizard (G-Max)',
            'pikachu-gmax'               => 'Pikachu (G-Max)',
        ];

        if (isset($specialCases[$nameLower])) {
            return $specialCases[$nameLower];
        }

        // Regras genéricas para encurtar partes conhecidas de formas/variações
        $parts = explode('-', $nameLower);
        if (count($parts) > 1) {
            $base = ucfirst($parts[0]);
            $suffixParts = array_slice($parts, 1);

            // Mapeamento de termos comuns
            $termMap = [
                'paldea' => 'P.',
                'alola' => 'Alola',
                'galar' => 'Galar',
                'hisui' => 'Hisui',
                'gmax' => 'G-Max',
                'mega' => 'Mega',
                'standard' => '',
                'combat' => 'Combat',
                'blaze' => 'Blaze',
                'aqua' => 'Aqua',
                'breed' => '',
                'single' => 'Single',
                'strike' => 'Strike',
                'rapid' => 'Rapid',
                'crowned' => 'Crowned',
                'origin' => 'Origin',
                'therian' => 'Therian',
                'incarnate' => 'Incarnate',
                'altered' => 'Altered',
                'zen' => 'Zen',
                'average' => 'Avg',
                'super' => 'Super',
                'small' => 'Small',
                'large' => 'Large',
                'dusk' => 'Dusk',
                'midnight' => 'Midnight',
                'midday' => 'Midday',
                'shadow' => 'Shadow',
                'ice' => 'Ice',
                'rider' => '',
            ];

            $newSuffixParts = [];
            foreach ($suffixParts as $part) {
                if (isset($termMap[$part])) {
                    if ($termMap[$part] !== '') {
                        $newSuffixParts[] = $termMap[$part];
                    }
                } else {
                    $newSuffixParts[] = ucfirst($part);
                }
            }

            if (empty($newSuffixParts)) {
                return $base;
            }

            return $base . ' (' . implode(' ', $newSuffixParts) . ')';
        }

        return ucfirst($nameLower);
    }
}
