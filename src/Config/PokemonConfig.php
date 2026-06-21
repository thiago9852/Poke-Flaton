<?php

namespace App\Config;

class PokemonConfig
{
    // Mapeamento padrão de variações e formas regionais de Pokémon
    public const DEFAULT_VARIATIONS = [
        // Alola
        //10100 => ['base_id' => 52, 'name' => 'meowth-alola'],
        //10101 => ['base_id' => 53, 'name' => 'persian-alola'],
        //10102 => ['base_id' => 26, 'name' => 'raichu-alola'],
        10103 => ['base_id' => 37, 'name' => 'vulpix-alola'],
        10104 => ['base_id' => 38, 'name' => 'ninetales-alola'],
        //10105 => ['base_id' => 50, 'name' => 'diglett-alola'],
        //10106 => ['base_id' => 51, 'name' => 'dugtrio-alola'],
        //10107 => ['base_id' => 27, 'name' => 'sandshrew-alola'],
        //10108 => ['base_id' => 28, 'name' => 'sandslash-alola'],
        //10109 => ['base_id' => 74, 'name' => 'geodude-alola'],
        //10110 => ['base_id' => 75, 'name' => 'graveler-alola'],
        //10111 => ['base_id' => 76, 'name' => 'golem-alola'],
        10112 => ['base_id' => 88, 'name' => 'grimer-alola'],
        10113 => ['base_id' => 89, 'name' => 'muk-alola'],
        10114 => ['base_id' => 103, 'name' => 'exeggutor-alola'],
        10115 => ['base_id' => 115, 'name' => 'marowak-alola'],

        // Galar
        
        //10161 => ['base_id' => 52, 'name' => 'meowth-galar'],
        //10162 => ['base_id' => 77, 'name' => 'ponyta-galar'],
        //10163 => ['base_id' => 78, 'name' => 'rapidash-galar'],
        //10164 => ['base_id' => 79, 'name' => 'slowpoke-galar'],
        //10165 => ['base_id' => 80, 'name' => 'slowbro-galar'],
        10166 => ['base_id' => 83, 'name' => 'farfetchd-galar'],
        //10167 => ['base_id' => 110, 'name' => 'weezing-galar'],
        //10168 => ['base_id' => 122, 'name' => 'mr-mime-galar'],
        //10169 => ['base_id' => 144, 'name' => 'articuno-galar'],
        //10170 => ['base_id' => 145, 'name' => 'zapdos-galar'],
        //10171 => ['base_id' => 146, 'name' => 'moltres-galar'],
        //10172 => ['base_id' => 199, 'name' => 'slowking-galar'],
        //10173 => ['base_id' => 222, 'name' => 'corsola-galar'],
        //10174 => ['base_id' => 263, 'name' => 'zigzagoon-galar'],
        //10175 => ['base_id' => 264, 'name' => 'linoone-galar'],
        //10176 => ['base_id' => 554, 'name' => 'darumaka-galar'],
        //10177 => ['base_id' => 555, 'name' => 'darmanitan-galar-standard'],
        //10179 => ['base_id' => 562, 'name' => 'yamask-galar'],
        //10180 => ['base_id' => 618, 'name' => 'stunfisk-galar'],
        10184 => ['base_id' => 849, 'name' => 'toxtricity-low-key'],

        // Hisui
        
        //10229 => ['base_id' => 58, 'name' => 'growlithe-hisui'],
        //10230 => ['base_id' => 59, 'name' => 'arcanine-hisui'],
        //10231 => ['base_id' => 100, 'name' => 'voltorb-hisui'],
        //10232 => ['base_id' => 101, 'name' => 'electrode-hisui'],
        //10233 => ['base_id' => 157, 'name' => 'typhlosion-hisui'],
        //10234 => ['base_id' => 211, 'name' => 'qwilfish-hisui'],
        10235 => ['base_id' => 215, 'name' => 'sneasel-hisui'],
        //10236 => ['base_id' => 503, 'name' => 'samurott-hisui'],
        //10237 => ['base_id' => 549, 'name' => 'lilligant-hisui'],
        //10238 => ['base_id' => 570, 'name' => 'zorua-hisui'],
        //10239 => ['base_id' => 571, 'name' => 'zoroark-hisui'],
        //10240 => ['base_id' => 628, 'name' => 'braviary-hisui'],
        10241 => ['base_id' => 705, 'name' => 'sliggoo-hisui'],
        10242 => ['base_id' => 706, 'name' => 'goodra-hisui'],
        //10243 => ['base_id' => 713, 'name' => 'avalugg-hisui'],
        

        // Palde
        10250 => ['base_id' => 128, 'name' => 'tauros-paldea-combat-breed'],
        10251 => ['base_id' => 128, 'name' => 'tauros-paldea-blaze-breed'],
        10252 => ['base_id' => 128, 'name' => 'tauros-paldea-aqua-breed'],
        //10253 => ['base_id' => 194, 'name' => 'wooper-paldea'],

        // Wormadam & Basculin
        10004 => ['base_id' => 413, 'name' => 'wormadam-sandy'],
        10005 => ['base_id' => 413, 'name' => 'wormadam-trash'],
        10016 => ['base_id' => 550, 'name' => 'basculin-blue-striped'],
        10247 => ['base_id' => 550, 'name' => 'basculin-white-striped'],
    ];

    // Lista completa de todos os IDs de variações padrão possíveis (para fins de sincronização/limpeza)
    public const ALL_DEFAULT_IDS = [
        // Alola
        10100, 10101, 10102, 10103, 10104, 10105, 10106, 10107, 10108, 10109, 10110, 10111, 10112, 10113, 10114, 10115,
        // Galar
        10161, 10162, 10163, 10164, 10165, 10166, 10167, 10168, 10169, 10170, 10171, 10172, 10173, 10174, 10175, 10176, 10177, 10179, 10180, 10184,
        // Hisui
        10229, 10230, 10231, 10232, 10233, 10234, 10235, 10236, 10237, 10238, 10239, 10240, 10241, 10242, 10243,
        // Paldea
        10250, 10251, 10252, 10253,
        // Wormadam & Basculin
        10004, 10005, 10016, 10247
    ];
}
