<?php

namespace App\Twig;

use App\Repository\PokemonLocationRepository;
use App\Service\TrainerProfileService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    private PokemonLocationRepository $pokemonLocationRepository;
    private TrainerProfileService $trainerProfileService;

    public function __construct(
        PokemonLocationRepository $pokemonLocationRepository,
        TrainerProfileService $trainerProfileService
    ) {
        $this->pokemonLocationRepository = $pokemonLocationRepository;
        $this->trainerProfileService = $trainerProfileService;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('pending_locations_count', [$this, 'getPendingLocationsCount']),
            new TwigFunction('avatar_url', [$this, 'getAvatarUrl']),
        ];
    }

    public function getPendingLocationsCount(): int
    {
        return $this->pokemonLocationRepository->count(['isApproved' => false]);
    }

    public function getAvatarUrl(?string $avatar): string
    {
        return $this->trainerProfileService->getAvatarUrl($avatar);
    }
}
