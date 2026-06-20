<?php

namespace App\Enum;

enum RegionEnum: string
{
    case Kanto = 'Kanto';
    case Johto = 'Johto';
    case Hoenn = 'Hoenn';
    case Sinnoh = 'Sinnoh';
    case Unova = 'Unova';
    case Kalos = 'Kalos';
    case Alola = 'Alola';
    case Galar = 'Galar';
    case Paldea = 'Paldea';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
