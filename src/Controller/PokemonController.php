<?php

namespace App\Controller;

use App\Service\PokeApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Enum\TypeEnum;

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

        $search = $request->query->get('q');
        $typeFilter = $request->query->get('type');

        $page = max(1, $request->query->getInt('page', 1));
        $limit = 24;
        $offset = ($page - 1) * $limit;

        if (!empty($typeFilter)) {
            // Filtragem por tipo: o serviço faz o slice e busca detalhes de apenas 24
            $result = $this->pokeApiService->getPokemonByType($typeFilter, $limit, $offset);
            $pokemons = $result['results'];
            $totalCount = $result['count'];
        } elseif (!empty($search)) {
            // Busca por termo: pega lista básica leve, filtra, faz slice e busca detalhes do lote de 24
            $search = strtolower(trim($search));
            $allBasicList = $this->pokeApiService->getPokemonBasicList(151); // 1ª Geração

            $filteredBasic = array_filter($allBasicList, function ($p) use ($search) {
                return str_contains($p['name'], $search) || strval($p['id']) === $search;
            });

            $totalCount = count($filteredBasic);
            $pagedBasic = array_slice($filteredBasic, $offset, $limit);

            $pokemons = $this->pokeApiService->getPokemonDetailsBatch($pagedBasic);
        } else {
            $result = $this->pokeApiService->getPokemonList($limit, $offset);
            $pokemons = $result['results'];
            $totalCount = min(151, $result['count']);
        }

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
            'allTypes' => TypeEnum::getCasesForModule('type'),
            'selectedType' => $typeFilter,
            'search' => $search,
        ]);
    }
}
