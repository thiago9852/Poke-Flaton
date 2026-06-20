<?php

namespace App\Config;

class TitleConfig
{
    public const DEFAULT_TITLES = [
        [
            'name' => 'Treinador Novato',
            'ribbon' => 'alert-ribbon.png',
            'requirement' => 'Desbloqueado por padrão.',
            'req_medal' => null,
            'req_tier' => null,
            'req_gold_count' => null,
            'req_rank_type' => null,
            'req_rank_pos' => null,
            'is_default' => 1
        ],
        [
            'name' => 'Cientista de Elite',
            'ribbon' => 'effort-ribbon.png',
            'requirement' => 'Medalha "Cientista" no nível Bronze.',
            'req_medal' => 'creator',
            'req_tier' => 'bronze',
            'req_gold_count' => null,
            'req_rank_type' => null,
            'req_rank_pos' => null,
            'is_default' => 0
        ],
        [
            'name' => 'Pesquisador de Elite',
            'ribbon' => 'classic-ribbon.png',
            'requirement' => 'Medalha "Pesquisador Pokémon" no nível Prata.',
            'req_medal' => 'pokedex',
            'req_tier' => 'silver',
            'req_gold_count' => null,
            'req_rank_type' => null,
            'req_rank_pos' => null,
            'is_default' => 0
        ],
        [
            'name' => 'Querido da Galera',
            'ribbon' => 'best-friends-ribbon.png',
            'requirement' => 'Medalha "Treinador Aclamado" no nível Bronze.',
            'req_medal' => 'acclaimed',
            'req_tier' => 'bronze',
            'req_gold_count' => null,
            'req_rank_type' => null,
            'req_rank_pos' => null,
            'is_default' => 0
        ],
        [
            'name' => 'Ídolo do PokeFlaton',
            'ribbon' => 'gorgeous-royal-ribbon.png',
            'requirement' => 'Medalha "Treinador Aclamado" no nível Ouro.',
            'req_medal' => 'acclaimed',
            'req_tier' => 'gold',
            'req_gold_count' => null,
            'req_rank_type' => null,
            'req_rank_pos' => null,
            'is_default' => 0
        ],
        [
            'name' => 'Mestre Pescador',
            'ribbon' => 'souvenir-ribbon.png',
            'requirement' => 'Medalha "Pescador" no nível Prata.',
            'req_medal' => 'fisherman',
            'req_tier' => 'silver',
            'req_gold_count' => null,
            'req_rank_type' => null,
            'req_rank_pos' => null,
            'is_default' => 0
        ],
        [
            'name' => 'Artista Vivillon',
            'ribbon' => 'artist-ribbon.png',
            'requirement' => 'Medalha "Coleção Vivillon" no nível Ouro.',
            'req_medal' => 'vivillon',
            'req_tier' => 'gold',
            'req_gold_count' => null,
            'req_rank_type' => null,
            'req_rank_pos' => null,
            'is_default' => 0
        ],
        [
            'name' => 'Mestre da Torre de TMs',
            'ribbon' => 'tower-master-ribbon.png',
            'requirement' => 'Medalha "Colecionador de TMs" no nível Ouro.',
            'req_medal' => 'collector',
            'req_tier' => 'gold',
            'req_gold_count' => null,
            'req_rank_type' => null,
            'req_rank_pos' => null,
            'is_default' => 0
        ],
        [
            'name' => 'Campeão de Galar',
            'ribbon' => 'galar-champion-ribbon.png',
            'requirement' => 'Medalha regional de "Galar/Hisui" no nível Ouro.',
            'req_medal' => 'galar',
            'req_tier' => 'gold',
            'req_gold_count' => null,
            'req_rank_type' => null,
            'req_rank_pos' => null,
            'is_default' => 0
        ],
        [
            'name' => 'Campeão de Unova',
            'ribbon' => 'champion-ribbon.png',
            'requirement' => 'Medalha regional de "Unova" no nível Ouro.',
            'req_medal' => 'unova',
            'req_tier' => 'gold',
            'req_gold_count' => null,
            'req_rank_type' => null,
            'req_rank_pos' => null,
            'is_default' => 0
        ],
        [
            'name' => 'Ranger Pokémon',
            'ribbon' => 'battle-champion-ribbon.png',
            'requirement' => 'Medalha "Gotta Catch Em All" no nível Prata.',
            'req_medal' => 'gotta-catch-all',
            'req_tier' => 'silver',
            'req_gold_count' => null,
            'req_rank_type' => null,
            'req_rank_pos' => null,
            'is_default' => 0
        ],
        [
            'name' => 'Mestre Pokémon',
            'ribbon' => 'master-rank-ribbon.png',
            'requirement' => 'Ter pelo menos 20 medalhas de Ouro.',
            'req_medal' => null,
            'req_tier' => null,
            'req_gold_count' => 20,
            'req_rank_type' => null,
            'req_rank_pos' => null,
            'is_default' => 0
        ],
        [
            'name' => 'Lenda da Popularidade',
            'ribbon' => 'royal-ribbon.png',
            'requirement' => '1º colocado no ranking de curtidas.',
            'req_medal' => null,
            'req_tier' => null,
            'req_gold_count' => null,
            'req_rank_type' => 'likes',
            'req_rank_pos' => 1,
            'is_default' => 0
        ],
        [
            'name' => 'Ícone da Comunidade',
            'ribbon' => 'red-ribbon.png',
            'requirement' => '2º colocado no ranking de curtidas.',
            'req_medal' => null,
            'req_tier' => null,
            'req_gold_count' => null,
            'req_rank_type' => 'likes',
            'req_rank_pos' => 2,
            'is_default' => 0
        ],
        [
            'name' => 'Querido do Público',
            'ribbon' => 'best-friends-ribbon.png',
            'requirement' => '3º colocado no ranking de curtidas.',
            'req_medal' => null,
            'req_tier' => null,
            'req_gold_count' => null,
            'req_rank_type' => 'likes',
            'req_rank_pos' => 3,
            'is_default' => 0
        ],
        [
            'name' => 'Campeão Supremo',
            'ribbon' => 'champion-ribbon.png',
            'requirement' => '1º colocado no ranking de medalhas.',
            'req_medal' => null,
            'req_tier' => null,
            'req_gold_count' => null,
            'req_rank_type' => 'medals',
            'req_rank_pos' => 1,
            'is_default' => 0
        ],
        [
            'name' => 'Mestre de Elite',
            'ribbon' => 'elite-four-ribbon.png',
            'requirement' => '2º colocado no ranking de medalhas.',
            'req_medal' => null,
            'req_tier' => null,
            'req_gold_count' => null,
            'req_rank_type' => 'medals',
            'req_rank_pos' => 2,
            'is_default' => 0
        ],
        [
            'name' => 'Especialista Lendário',
            'ribbon' => 'classic-ribbon.png',
            'requirement' => '3º colocado no ranking de medalhas.',
            'req_medal' => null,
            'req_tier' => null,
            'req_gold_count' => null,
            'req_rank_type' => 'medals',
            'req_rank_pos' => 3,
            'is_default' => 0
        ]
    ];
}
