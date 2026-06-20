<?php

namespace App\Enum;

enum EvolutionStone: string
{
    case FireStone = 'Fire Stone';
    case WaterStone = 'Water Stone';
    case ThunderStone = 'Thunder Stone';
    case LeafStone = 'Leaf Stone';
    case MoonStone = 'Moon Stone';
    case SunStone = 'Sun Stone';
    case DuskStone = 'Dusk Stone';
    case IceStone = 'Ice Stone';
    case EarthStone = 'Earth Stone';
    case RockStone = 'Rock Stone';
    case InsectStone = 'Insect Stone';
    case DragonStone = 'Dragon Stone';
    case FairyStone = 'Fairy Stone';
    case PsychicStone = 'Psychic Stone';
    case PunchStone = 'Punch Stone';
    case HeartStone = 'Heart Stone';

    public function getLabel(): string
    {
        return match ($this) {
            self::FireStone => 'Fire Stone',
            self::WaterStone => 'Water Stone',
            self::ThunderStone => 'Thunder Stone',
            self::LeafStone => 'Leaf Stone',
            self::MoonStone => 'Moon Stone',
            self::SunStone => 'Sun Stone',
            self::DuskStone => 'Dusk Stone',
            self::IceStone => 'Ice Stone',
            self::EarthStone => 'Earth Stone',
            self::RockStone => 'Rock Stone',
            self::InsectStone => 'Insect Stone',
            self::DragonStone => 'Dragon Stone',
            self::FairyStone => 'Fairy Stone',
            self::PsychicStone => 'Psychic Stone',
            self::PunchStone => 'Punch Stone',
            self::HeartStone => 'Heart Stone',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
