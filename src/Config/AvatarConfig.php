<?php

namespace App\Config;

class AvatarConfig
{
    // Avatares de recompensa bloqueados por medalhas de ouro
    public const AVATAR_REWARDS = [
        'Iris.png' => ['medal' => 'type_dragon', 'tier' => 'gold', 'label' => 'Medalha de Dragão de Ouro'],
        'Wattson.png' => ['medal' => 'type_electric', 'tier' => 'gold', 'label' => 'Medalha de Elétrico de Ouro'],
        'Marlon.png' => ['medal' => 'fisherman', 'tier' => 'gold', 'label' => 'Medalha de Pescador de Ouro'],
        'Roxie.png' => ['medal' => 'type_poison', 'tier' => 'gold', 'label' => 'Medalha de Venenoso de Ouro'],
        'Roxanne.png' => ['medal' => 'type_rock', 'tier' => 'gold', 'label' => 'Medalha de Pedra de Ouro'],
        'Steven.png' => ['medal' => 'hoenn', 'tier' => 'gold', 'label' => 'Medalha de Hoenn de Ouro'],
        'Winona.png' => ['medal' => 'type_flying', 'tier' => 'gold', 'label' => 'Medalha de Voador de Ouro'],
        'Flannery.png' => ['medal' => 'type_fire', 'tier' => 'gold', 'label' => 'Medalha de Fogo de Ouro'],
        'Brawly.png' => ['medal' => 'type_fighting', 'tier' => 'gold', 'label' => 'Medalha de Lutador de Ouro'],
        'Wallace.png' => ['medal' => 'type_water', 'tier' => 'gold', 'label' => 'Medalha de Água de Ouro'],
        'Benga.png' => ['medal' => 'unova', 'tier' => 'gold', 'label' => 'Medalha de Unova de Ouro'],
        'Ghetsis.png' => ['medal' => 'legendary', 'tier' => 'gold', 'label' => 'Medalha de Mestre Lendário de Ouro'],
        'Colress.png' => ['medal' => 'collector', 'tier' => 'gold', 'label' => 'Medalha de Colecionador de TMs de Ouro'],
        'Zinzolin.png' => ['medal' => 'type_ice', 'tier' => 'gold', 'label' => 'Medalha de Gelo de Ouro'],
        'Bellelba.png' => ['medal' => 'vivillon', 'tier' => 'gold', 'label' => 'Medalha de Coleção Vivillon de Ouro'],
        'Tate.png' => ['medal' => 'type_psychic', 'tier' => 'gold', 'label' => 'Medalha de Psíquico de Ouro'],
        'Liza.png' => ['medal' => 'type_fairy', 'tier' => 'gold', 'label' => 'Medalha de Fada de Ouro'],
        'Juan.png' => ['medal' => 'type_normal', 'tier' => 'gold', 'label' => 'Medalha de Normal de Ouro'],
        'Rood.png' => ['medal' => 'type_grass', 'tier' => 'gold', 'label' => 'Medalha de Grama de Ouro'],
        'Shadow_Triad.png' => ['medal' => 'type_dark', 'tier' => 'gold', 'label' => 'Medalha de Sombrio de Ouro'],
    ];

    public const PKM_AVATARS = [
        'Vivillon-Pokeball.png',
        'blastoise.png',
        'charizard.png',
        'empoleon.png',
        'garchomp.png',
        'gardevoir.png',
        'glaceon.png',
        'lucario.png',
        'luxray.png',
        'metagross.png',
        'tyranitar.png',
        'umbreon.png',
        'venusaur.png',
    ];
}
