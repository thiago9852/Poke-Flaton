<?php

namespace App\Twig;

use App\Repository\PokemonLocationRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    private PokemonLocationRepository $pokemonLocationRepository;

    public function __construct(PokemonLocationRepository $pokemonLocationRepository)
    {
        $this->pokemonLocationRepository = $pokemonLocationRepository;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('pending_locations_count', [$this, 'getPendingLocationsCount']),
        ];
    }

    public function getPendingLocationsCount(): int
    {
        return $this->pokemonLocationRepository->count(['isApproved' => false]);
    }
}
