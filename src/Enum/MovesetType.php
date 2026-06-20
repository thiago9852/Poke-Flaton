<?php

namespace App\Enum;

enum MovesetType: string
{
    case Standard = 'padrao';
    case PvP = 'pvp';
    case DG = 'dg';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
