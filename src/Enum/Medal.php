<?php

namespace App\Enum;

enum Medal: string
{
    // Categoria 1: Atividade e Comunidade
    case Creator = 'creator';
    case Acclaimed = 'acclaimed';
    case Collector = 'collector';
    case Friendship = 'friendship';
    case Popular = 'popular';
    case GottaCatchAll = 'gotta-catch-all';

    // Categoria 2: Enciclopédia Pokémon
    case Pokedex = 'pokedex';
    case Fisherman = 'fisherman';
    case Vivillon = 'vivillon';
    case Pikachu = 'pikachu';
    case Youngster = 'youngster';

    // Categoria 3: Pokédex Regional
    case Kanto = 'kanto';
    case Johto = 'johto';
    case Hoenn = 'hoenn';
    case Sinnoh = 'sinnoh';
    case Unova = 'unova';
    case Kalos = 'kalos';
    case Alola = 'alola';
    case Galar = 'galar';
    case Paldea = 'paldea';

    // Categoria 4: Captura por Tipo
    case TypeNormal = 'type_normal';
    case TypeFire = 'type_fire';
    case TypeWater = 'type_water';
    case TypeGrass = 'type_grass';
    case TypeElectric = 'type_electric';
    case TypeIce = 'type_ice';
    case TypeFighting = 'type_fighting';
    case TypePoison = 'type_poison';
    case TypeGround = 'type_ground';
    case TypeFlying = 'type_flying';
    case TypePsychic = 'type_psychic';
    case TypeBug = 'type_bug';
    case TypeRock = 'type_rock';
    case TypeGhost = 'type_ghost';
    case TypeDragon = 'type_dragon';
    case TypeSteel = 'type_steel';
    case TypeDark = 'type_dark';
    case TypeFairy = 'type_fairy';

    public function getGroupName(): string
    {
        return match ($this) {
            self::Creator,
            self::Acclaimed,
            self::Collector,
            self::Friendship,
            self::Popular,
            self::GottaCatchAll => 'Atividade e Comunidade',

            self::Pokedex,
            self::Fisherman,
            self::Vivillon,
            self::Pikachu,
            self::Youngster => 'Enciclopédia Pokémon',

            self::Kanto,
            self::Johto,
            self::Hoenn,
            self::Sinnoh,
            self::Unova,
            self::Kalos,
            self::Alola,
            self::Galar,
            self::Paldea => 'Pokédex Regional',

            default => 'Captura por Tipo',
        };
    }

    public function getTitle(): string
    {
        return match ($this) {
            self::Creator => 'Cientista',
            self::Acclaimed => 'Treinador Aclamado',
            self::Collector => 'Colecionador de TMs',
            self::Friendship => 'Laço de Amizade',
            self::Popular => 'Estrela da Comunidade',
            self::GottaCatchAll => 'Gotta Catch Em All',

            self::Pokedex => 'Pesquisador Pokémon',
            self::Fisherman => 'Pescador',
            self::Vivillon => 'Coleção Vivillon',
            self::Pikachu => 'Fã de Pikachu',
            self::Youngster => 'Estilo Jovem',

            self::Kanto => 'Kanto',
            self::Johto => 'Johto',
            self::Hoenn => 'Hoenn',
            self::Sinnoh => 'Sinnoh',
            self::Unova => 'Unova',
            self::Kalos => 'Kalos',
            self::Alola => 'Alola',
            self::Galar => 'Galar/Hisui',
            self::Paldea => 'Paldea',

            self::TypeNormal => 'Estudante',
            self::TypeFire => 'Esquentado',
            self::TypeWater => 'Nadador',
            self::TypeGrass => 'Jardineiro',
            self::TypeElectric => 'Roqueiro',
            self::TypeIce => 'Esquiador',
            self::TypeFighting => 'Cinturão Negro',
            self::TypePoison => 'Garota Punk',
            self::TypeGround => 'Maníaco das Ruínas',
            self::TypeFlying => 'Ornitólogo',
            self::TypePsychic => 'Médium',
            self::TypeBug => 'Caçador de Insetos',
            self::TypeRock => 'Montanhista',
            self::TypeGhost => 'Místico',
            self::TypeDragon => 'Domador de Dragões',
            self::TypeSteel => 'Agente do Pátio',
            self::TypeDark => 'Delinquente',
            self::TypeFairy => 'Conto de Fadas',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Creator => 'Crie novos movesets recomendados para Pokémons.',
            self::Acclaimed => 'Soma total de curtidas recebidas em seus movesets.',
            self::Collector => 'Quantidade de TMs registradas em sua mochila.',
            self::Friendship => 'Quantidade de outros treinadores que você está seguindo.',
            self::Popular => 'Quantidade de treinadores que seguem seu perfil.',
            self::GottaCatchAll => 'Capture o maior número de espécies de Pokémon que puder!',

            self::Pokedex => 'Número de espécies de Pokémon capturadas e registradas.',
            self::Fisherman => 'Quantidade de Pokémon do tipo Água capturados.',
            self::Vivillon => 'Diferentes padrões de Vivillon capturados ao redor do mundo.',
            self::Pikachu => 'Capture um Pikachu para provar sua admiração.',
            self::Youngster => 'Capture um Rattata (o melhor Rattata!).',

            self::Kanto => 'Espécies descobertas na região de Kanto.',
            self::Johto => 'Espécies descobertas na região de Johto.',
            self::Hoenn => 'Espécies descobertas na região de Hoenn.',
            self::Sinnoh => 'Espécies descobertas na região de Sinnoh.',
            self::Unova => 'Espécies descobertas na região de Unova.',
            self::Kalos => 'Espécies descobertas na região de Kalos.',
            self::Alola => 'Espécies descobertas na região de Alola.',
            self::Galar => 'Espécies descobertas na região de Galar e Hisui.',
            self::Paldea => 'Espécies descobertas na região de Paldea.',

            self::TypeNormal => 'Capture Pokémon do tipo Normal.',
            self::TypeFire => 'Capture Pokémon do tipo Fogo.',
            self::TypeWater => 'Capture Pokémon do tipo Água.',
            self::TypeGrass => 'Capture Pokémon do tipo Grama.',
            self::TypeElectric => 'Capture Pokémon do tipo Elétrico.',
            self::TypeIce => 'Capture Pokémon do tipo Gelo.',
            self::TypeFighting => 'Capture Pokémon do tipo Lutador.',
            self::TypePoison => 'Capture Pokémon do tipo Venenoso.',
            self::TypeGround => 'Capture Pokémon do tipo Terra.',
            self::TypeFlying => 'Capture Pokémon do tipo Voador.',
            self::TypePsychic => 'Capture Pokémon do tipo Psíquico.',
            self::TypeBug => 'Capture Pokémon do tipo Inseto.',
            self::TypeRock => 'Capture Pokémon do tipo Pedra.',
            self::TypeGhost => 'Capture Pokémon do tipo Fantasma.',
            self::TypeDragon => 'Capture Pokémon do tipo Dragão.',
            self::TypeSteel => 'Capture Pokémon do tipo Aço.',
            self::TypeDark => 'Capture Pokémon do tipo Sombrio.',
            self::TypeFairy => 'Capture Pokémon do tipo Fada.',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Creator => 'fa-flask',
            self::Acclaimed => 'fa-heart',
            self::Collector => 'fa-compact-disc',
            self::Friendship => 'fa-people-group',
            self::Popular => 'fa-star',
            self::GottaCatchAll => 'fa-trophy',

            self::Pokedex => 'fa-database',
            self::Fisherman => 'fa-fish-fins',
            self::Vivillon => 'fa-leaf',
            self::Pikachu => 'fa-bolt',
            self::Youngster => 'fa-paw',

            self::Kanto,
            self::Johto,
            self::Hoenn,
            self::Sinnoh,
            self::Unova,
            self::Kalos,
            self::Alola,
            self::Galar,
            self::Paldea => 'fa-map-location-dot',

            self::TypeNormal => 'fa-circle',
            self::TypeFire => 'fa-fire',
            self::TypeWater => 'fa-droplet',
            self::TypeGrass => 'fa-leaf',
            self::TypeElectric => 'fa-bolt-lightning',
            self::TypeIce => 'fa-snowflake',
            self::TypeFighting => 'fa-hand-fist',
            self::TypePoison => 'fa-skull-crossbones',
            self::TypeGround => 'fa-mountain-sun',
            self::TypeFlying => 'fa-wind',
            self::TypePsychic => 'fa-eye',
            self::TypeBug => 'fa-bug',
            self::TypeRock => 'fa-gem',
            self::TypeGhost => 'fa-ghost',
            self::TypeDragon => 'fa-dragon',
            self::TypeSteel => 'fa-shield',
            self::TypeDark => 'fa-moon',
            self::TypeFairy => 'fa-wand-magic-sparkles',
        };
    }

    /**
     * @return array{bronze: int, silver: int, gold: int}
     */
    public function getMilestones(): array
    {
        return match ($this) {
            self::Creator => ['bronze' => 1, 'silver' => 5, 'gold' => 15],
            self::Acclaimed => ['bronze' => 5, 'silver' => 25, 'gold' => 100],
            self::Collector => ['bronze' => 10, 'silver' => 30, 'gold' => 60],
            self::Friendship => ['bronze' => 2, 'silver' => 5, 'gold' => 15],
            self::Popular => ['bronze' => 1, 'silver' => 3, 'gold' => 10],
            self::GottaCatchAll => ['bronze' => 50, 'silver' => 150, 'gold' => 300],

            self::Pokedex => ['bronze' => 5, 'silver' => 20, 'gold' => 50],
            self::Fisherman => ['bronze' => 3, 'silver' => 10, 'gold' => 25],
            self::Vivillon => ['bronze' => 3, 'silver' => 8, 'gold' => 15],
            self::Pikachu => ['bronze' => 1, 'silver' => 2, 'gold' => 3],
            self::Youngster => ['bronze' => 1, 'silver' => 2, 'gold' => 3],

            self::Kanto,
            self::Johto,
            self::Hoenn,
            self::Sinnoh,
            self::Unova => ['bronze' => 3, 'silver' => 10, 'gold' => 30],

            self::Kalos,
            self::Alola,
            self::Galar,
            self::Paldea => ['bronze' => 3, 'silver' => 8, 'gold' => 20],

            default => ['bronze' => 2, 'silver' => 5, 'gold' => 12],
        };
    }

    public function getBadgeCategory(): string
    {
        return str_starts_with($this->value, 'type_') ? 'type' : 'general';
    }

    public function getBadgeSlug(): string
    {
        return match ($this) {
            self::Creator => 'scientist',
            self::Acclaimed => 'rising-star',
            self::Collector => 'collector',
            self::Friendship => 'friend-finder',
            self::Popular => 'idol',
            self::GottaCatchAll => 'pokemon-ranger',

            self::Pokedex => 'ace-trainer',
            self::Fisherman => 'fisher',
            self::Vivillon => 'vivillon-collector',
            self::Pikachu => 'pikachu-fan',
            self::Youngster => 'gentleman',

            self::Kanto => 'kanto',
            self::Johto => 'johto',
            self::Hoenn => 'hoenn',
            self::Sinnoh => 'sinnoh',
            self::Unova => 'unova',
            self::Kalos => 'kalos',
            self::Alola => 'alola',
            self::Galar => 'galar',
            self::Paldea => 'paldea',

            self::TypeNormal => 'schoolkid',
            self::TypeFire => 'kindler',
            self::TypeWater => 'swimmer',
            self::TypeGrass => 'gardener',
            self::TypeElectric => 'rocker',
            self::TypeIce => 'skier',
            self::TypeFighting => 'black-belt',
            self::TypePoison => 'punk-girl',
            self::TypeGround => 'ruin-maniac',
            self::TypeFlying => 'bird-keeper',
            self::TypePsychic => 'psychic',
            self::TypeBug => 'bug-catcher',
            self::TypeRock => 'hiker',
            self::TypeGhost => 'hex-maniac',
            self::TypeDragon => 'dragon-tamer',
            self::TypeSteel => 'rail-staff',
            self::TypeDark => 'delinquent',
            self::TypeFairy => 'fairy-tale-girl',
        };
    }

    public function getBadgePath(string $tier): string
    {
        return $this->getBadgeCategory() . '/' . $tier . '/' . $this->getBadgeSlug() . '.webp';
    }

    public static function getDefinitionsGrouped(): array
    {
        $groups = [];
        foreach (self::cases() as $medal) {
            $groups[$medal->getGroupName()][] = [
                'key' => $medal->value,
                'title' => $medal->getTitle(),
                'badge' => $medal->getBadgePath('gold')
            ];
        }
        return $groups;
    }
}
