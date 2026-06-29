<?php

namespace App\Controller;

use App\Entity\Map;
use App\Entity\MapPokemon;
use App\Repository\MapRepository;
use App\Repository\MapPokemonRepository;
use App\Service\PokeApiService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MapController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private PokeApiService $pokeApiService;
    private MapRepository $mapRepository;
    private MapPokemonRepository $mapPokemonRepository;
    private string $projectDir;

    public function __construct(
        EntityManagerInterface $entityManager,
        PokeApiService $pokeApiService,
        MapRepository $mapRepository,
        MapPokemonRepository $mapPokemonRepository,
        #[Autowire('%kernel.project_dir%')] string $projectDir
    ) {
        $this->entityManager = $entityManager;
        $this->pokeApiService = $pokeApiService;
        $this->mapRepository = $mapRepository;
        $this->mapPokemonRepository = $mapPokemonRepository;
        $this->projectDir = $projectDir;
    }

    #[Route('/map', name: 'app_map', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $maps = $this->mapRepository->findBy([], ['name' => 'ASC']);
        
        // Determinar o mapa ativo
        $activeMapId = $request->query->get('mapId');
        $activeMap = null;

        if ($activeMapId) {
            $activeMap = $this->mapRepository->find($activeMapId);
        }

        $activeMapPokemons = [];
        if ($activeMap) {
            $activeMapPokemons = $this->mapPokemonRepository->findBy(['map' => $activeMap], ['createdAt' => 'DESC']);
        }

        // Lista básica de Pokémon para autocomplete no cadastro
        $pokemonBasicList = $this->pokeApiService->getPokemonBasicList();

        return $this->render('map/index.html.twig', [
            'maps' => $maps,
            'activeMap' => $activeMap,
            'activeMapPokemons' => $activeMapPokemons,
            'pokemonList' => $pokemonBasicList,
        ]);
    }

    #[Route('/admin/map/add', name: 'app_admin_map_add', methods: ['POST'])]
    public function addMap(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $name = trim($request->request->get('name', ''));
        $file = $request->files->get('image');

        if (empty($name)) {
            $this->addFlash('error', 'O nome do mapa não pode ser vazio.');
            return $this->redirectToRoute('app_map');
        }

        if (!$file) {
            $this->addFlash('error', 'A imagem do mapa é obrigatória.');
            return $this->redirectToRoute('app_map');
        }

        // Validar se é uma imagem
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
            $this->addFlash('error', 'Formato de imagem inválido. Formatos aceitos: JPEG, PNG, GIF, WEBP.');
            return $this->redirectToRoute('app_map');
        }

        $uploadsDir = $this->projectDir . '/public/uploads/maps';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0777, true);
        }

        $newFilename = uniqid() . '.' . $file->guessExtension();

        try {
            $file->move($uploadsDir, $newFilename);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Falha ao fazer upload da imagem.');
            return $this->redirectToRoute('app_map');
        }

        $map = new Map();
        $map->setName($name)
            ->setImagePath($newFilename);

        $this->entityManager->persist($map);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('Mapa "%s" cadastrado com sucesso!', $name));

        return $this->redirectToRoute('app_map', ['mapId' => $map->getId()]);
    }

    #[Route('/admin/map/{id}/delete', name: 'app_admin_map_delete', methods: ['POST'])]
    public function deleteMap(int $id, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $map = $this->mapRepository->find($id);
        if (!$map) {
            $this->addFlash('error', 'Mapa não encontrado.');
            return $this->redirectToRoute('app_map');
        }

        // Validar CSRF
        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete-map-' . $id, $submittedToken)) {
            $this->addFlash('error', 'Token de segurança inválido.');
            return $this->redirectToRoute('app_map');
        }

        $name = $map->getName();
        $imageFilepath = $this->projectDir . '/public/uploads/maps/' . $map->getImagePath();
        
        $this->entityManager->remove($map);
        $this->entityManager->flush();

        // Apagar imagem física se existir
        if (file_exists($imageFilepath)) {
            unlink($imageFilepath);
        }

        $this->addFlash('success', sprintf('Mapa "%s" excluído com sucesso!', $name));

        return $this->redirectToRoute('app_map');
    }

    #[Route('/map/pokemon/add', name: 'app_map_pokemon_add', methods: ['POST'])]
    public function addPokemon(Request $request): JsonResponse
    {
        $mapId = $request->request->get('mapId');
        $pokemonName = trim($request->request->get('pokemonName', ''));
        $pokemonId = $request->request->get('pokemonId');
        $latitude = $request->request->get('latitude'); // Tratado como coordenada Y da imagem
        $longitude = $request->request->get('longitude'); // Tratado como coordenada X da imagem
        $notes = trim($request->request->get('notes', ''));
        $token = $request->request->get('_token');

        // Validar CSRF
        if (!$this->isCsrfTokenValid('add-map-pokemon', $token)) {
            return new JsonResponse(['error' => 'Token CSRF inválido.'], 400);
        }

        if (empty($pokemonName) || !$pokemonId || $latitude === null || $longitude === null || !$mapId) {
            return new JsonResponse(['error' => 'Dados incompletos fornecidos.'], 400);
        }

        $map = $this->mapRepository->find($mapId);
        if (!$map) {
            return new JsonResponse(['error' => 'Mapa não encontrado.'], 404);
        }

        $mapPokemon = new MapPokemon();
        $mapPokemon->setMap($map)
            ->setPokemonName($pokemonName)
            ->setPokemonId((int)$pokemonId)
            ->setLatitude((float)$latitude)
            ->setLongitude((float)$longitude)
            ->setNotes(!empty($notes) ? $notes : null)
            ->setUser($this->getUser()); // Will be null if the user is not logged in

        $this->entityManager->persist($mapPokemon);
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'pokemon' => [
                'id' => $mapPokemon->getId(),
                'pokemonId' => $mapPokemon->getPokemonId(),
                'pokemonName' => ucfirst($mapPokemon->getPokemonName()),
                'latitude' => $mapPokemon->getLatitude(),
                'longitude' => $mapPokemon->getLongitude(),
                'notes' => $mapPokemon->getNotes(),
                'username' => $mapPokemon->getUser() ? $mapPokemon->getUser()->getUsername() : 'Anônimo',
                'createdAt' => $mapPokemon->getCreatedAt()->format('d/m/Y H:i'),
                'canDelete' => $this->isGranted('ROLE_ADMIN')
            ]
        ]);
    }

    #[Route('/map/pokemon/{id}/delete', name: 'app_map_pokemon_delete', methods: ['POST'])]
    public function deletePokemon(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $mapPokemon = $this->mapPokemonRepository->find($id);
        if (!$mapPokemon) {
            return new JsonResponse(['error' => 'Marcador não encontrado.'], 404);
        }

        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete-map-pokemon-' . $id, $token)) {
            return new JsonResponse(['error' => 'Token CSRF inválido.'], 400);
        }

        $this->entityManager->remove($mapPokemon);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
    }
}
