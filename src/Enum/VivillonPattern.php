<?php

namespace App\Enum;

enum VivillonPattern: string
{
    case Meadow = 'meadow';
    case Archipelago = 'archipelago';
    case Continental = 'continental';
    case Elegant = 'elegant';
    case Garden = 'garden';
    case HighPlains = 'high-plains';
    case IcySnow = 'icy-snow';
    case Jungle = 'jungle';
    case Marine = 'marine';
    case Modern = 'modern';
    case Monsoon = 'monsoon';
    case Ocean = 'ocean';
    case Polar = 'polar';
    case River = 'river';
    case Sandstorm = 'sandstorm';
    case Savanna = 'savanna';
    case Sun = 'sun';
    case Tundra = 'tundra';
    case Fancy = 'fancy';
    case PokeBall = 'poke-ball';

    public function getLabel(): string
    {
        return match ($this) {
            self::Meadow => 'Meadow',
            self::Archipelago => 'Archipelago',
            self::Continental => 'Continental',
            self::Elegant => 'Elegant',
            self::Garden => 'Garden',
            self::HighPlains => 'High Plains',
            self::IcySnow => 'Icy Snow',
            self::Jungle => 'Jungle',
            self::Marine => 'Marine',
            self::Modern => 'Modern',
            self::Monsoon => 'Monsoon',
            self::Ocean => 'Ocean',
            self::Polar => 'Polar',
            self::River => 'River',
            self::Sandstorm => 'Sandstorm',
            self::Savanna => 'Savanna',
            self::Sun => 'Sun',
            self::Tundra => 'Tundra',
            self::Fancy => 'Fancy',
            self::PokeBall => 'Poké Ball',
        };
    }

    public function getSpriteFilename(): string
    {
        return match ($this) {
            self::HighPlains => 'high_plains',
            self::IcySnow => 'icy_snow',
            self::PokeBall => 'pokeball',
            default => $this->value,
        };
    }

    public function getSpriteUrl(): string
    {
        return 'https://raw.githubusercontent.com/PokeMiners/pogo_assets/master/Images/Badges/Vivillon/' . $this->getSpriteFilename() . '.png';
    }

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
