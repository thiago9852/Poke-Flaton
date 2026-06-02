<?php

namespace App\Controller;

use App\Service\PokeApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PokemonController extends AbstractController
{
    private PokeApiService $pokeApiService;

    public function __construct(PokeApiService $pokeApiService)
    {
        $this->pokeApiService = $pokeApiService;
    }

    #[Route('/', name: 'app_home')]
    public function home(): Response
    {
        return $this->render('index/index.html.twig', []);
    }

    #[Route('/pokemons', name: 'app_pokemon_index')]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 24;
        $offset = ($page - 1) * $limit;

        $result = $this->pokeApiService->getPokemonList($limit, $offset);
        $pokemons = $result['results'];
        $totalCount = min(151, $result['count']);

        // Ajustar o slice se passar de 151
        if ($offset + $limit > 151) {
            $sliceLimit = max(0, 151 - $offset);
            $pokemons = array_slice($pokemons, 0, $sliceLimit);
        }

        $lastPage = (int) ceil($totalCount / $limit);

        return $this->render('pokemon/index.html.twig', [
            'pokemons' => $pokemons,
            'currentPage' => $page,
            'lastPage' => $lastPage,
        ]);
    }
}
