<?php

declare(strict_types=1);

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
    case Dark = 'dark';
}
