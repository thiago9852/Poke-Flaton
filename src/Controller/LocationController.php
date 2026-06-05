<?php

namespace App\Controller;

use App\Entity\PokemonLocation;
use App\Service\PokeApiService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LocationController extends AbstractController
{
    private PokeApiService $pokeApiService;
    private EntityManagerInterface $entityManager;

    public function __construct(PokeApiService $pokeApiService, EntityManagerInterface $entityManager)
    {
        $this->pokeApiService = $pokeApiService;
        $this->entityManager = $entityManager;
    }

    #[Route('/pokemon/{name}/location/add', name: 'app_location_add', methods: ['POST'])]
    public function add(string $name, Request $request): Response
    {
        try {
            $pokemon = $this->pokeApiService->getPokemonDetails($name);
        } catch (\Exception $e) {
            throw $this->createNotFoundException('Pokémon não encontrado.');
        }

        $locationName = trim($request->request->get('locationName', ''));

        if (empty($locationName)) {
            $this->addFlash('error', 'O nome da localização não pode ser vazio.');
        } elseif (strlen($locationName) > 150) {
            $this->addFlash('error', 'O nome da localização não pode ter mais de 150 caracteres.');
        } else {
            $pokemonLocation = new PokemonLocation();
            $pokemonLocation->setPokemonName($pokemon['name']);
            $pokemonLocation->setLocationName($locationName);

            $this->entityManager->persist($pokemonLocation);
            $this->entityManager->flush();

            $this->addFlash('success', 'Localização adicionada com sucesso!');
        }

        return $this->redirectToRoute('app_pokemon_detail', ['name' => $pokemon['name']]);
    }
}
