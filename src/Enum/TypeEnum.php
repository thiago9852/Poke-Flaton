<?php

// src/Enum
namespace App\Enum;

enum TypeEnum: string
{
    case Normal = 'normal';
    case Fire = 'fire';
    case Water = 'water';
    case Grass = 'grass';
    case Electric = 'electric';
    case Ice = 'ice';
    case Fighting = 'fighting';
    case Poison = 'poison';
    case Ground = 'ground';
    case Flying = 'flying';
    case Psychic = 'psychic';
    case Bug = 'bug';
    case Rock = 'rock';
    case Ghost = 'ghost';
    case Dragon = 'dragon';
    case Steel = 'steel';
    case Fairy = 'fairy';

    public static function getCasesForModule(string $moduleName): array
    {
        return match ($moduleName) {
            'type' => [
                self::Normal,
                self::Fire,
                self::Water,
                self::Grass,
                self::Electric,
                self::Ice,
                self::Fighting,
                self::Poison,
                self::Ground,
                self::Flying,
                self::Psychic,
                self::Bug,
                self::Rock,
                self::Ghost,
                self::Dragon,
                self::Steel,
                self::Fairy,
            ],
        };
    }
}