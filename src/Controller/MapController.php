<?php

namespace App\Controller;

use App\Entity\Map;
use App\Entity\MapPokemon;
use App\Entity\MapPortal;
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
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Asset\Packages;

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
        $maps = $this->mapRepository->findBy(['isSubmap' => false], ['name' => 'ASC']);
        $allMaps = $this->mapRepository->findBy([], ['name' => 'ASC']);
        
        // Determinar o mapa ativo
        $activeMapId = $request->query->get('mapId');
        $activeMap = null;

        if ($activeMapId) {
            $activeMap = $this->mapRepository->find($activeMapId);
        }

        $activeMapPokemons = [];
        $activeMapPortals = [];
        if ($activeMap) {
            $activeMapPokemons = $this->mapPokemonRepository->findBy(['map' => $activeMap], ['createdAt' => 'DESC']);
            $activeMapPortals = $activeMap->getPortals();
        }

        // Lista básica de Pokémon para autocomplete no cadastro
        $pokemonBasicList = $this->pokeApiService->getPokemonBasicList();

        return $this->render('map/index.html.twig', [
            'maps' => $maps,
            'allMaps' => $allMaps,
            'activeMap' => $activeMap,
            'activeMapPokemons' => $activeMapPokemons,
            'activeMapPortals' => $activeMapPortals,
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

        $uploadsDir = $this->projectDir . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'maps';
        $uploadsDir = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $uploadsDir);
        
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0777, true);
        }

        $newFilename = uniqid() . '.' . $file->guessExtension();
        $targetPath = $uploadsDir . DIRECTORY_SEPARATOR . $newFilename;

        try {
            if (!move_uploaded_file($file->getPathname(), $targetPath)) {
                throw new \Exception('Não foi possível mover o arquivo de upload para o destino final.');
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Falha ao fazer upload da imagem: ' . $e->getMessage());
            return $this->redirectToRoute('app_map');
        }

        $isSubmap = (bool) $request->request->get('isSubmap', false);

        $map = new Map();
        $map->setName($name)
            ->setImagePath($newFilename)
            ->setIsSubmap($isSubmap);

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

    #[Route('/admin/map/portal/add', name: 'app_admin_map_portal_add', methods: ['POST'])]
    public function addPortal(Request $request, CsrfTokenManagerInterface $csrfTokenManager): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $parentMapId = $request->request->get('parentMapId');
        $targetMapId = $request->request->get('targetMapId');
        $latitude = $request->request->get('latitude');
        $longitude = $request->request->get('longitude');
        $token = $request->request->get('_token');

        if (!$this->isCsrfTokenValid('add-map-portal', $token)) {
            return new JsonResponse(['error' => 'Token CSRF inválido.'], 400);
        }

        if (!$parentMapId || !$targetMapId || $latitude === null || $longitude === null) {
            return new JsonResponse(['error' => 'Dados incompletos.'], 400);
        }

        if ($parentMapId == $targetMapId) {
            return new JsonResponse(['error' => 'Um mapa não pode apontar para si mesmo.'], 400);
        }

        $parentMap = $this->mapRepository->find($parentMapId);
        $targetMap = $this->mapRepository->find($targetMapId);

        if (!$parentMap || !$targetMap) {
            return new JsonResponse(['error' => 'Mapas não encontrados.'], 404);
        }

        $portal = new MapPortal();
        $portal->setParentMap($parentMap)
            ->setTargetMap($targetMap)
            ->setLatitude((float)$latitude)
            ->setLongitude((float)$longitude);

        $this->entityManager->persist($portal);
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'portal' => [
                'id' => $portal->getId(),
                'parentMapId' => $parentMap->getId(),
                'targetMapId' => $targetMap->getId(),
                'targetMapName' => $targetMap->getName(),
                'latitude' => $portal->getLatitude(),
                'longitude' => $portal->getLongitude(),
                'createdAt' => $portal->getCreatedAt()->format('d/m/Y H:i'),
                'csrfToken' => $csrfTokenManager->getToken('delete-map-portal-' . $portal->getId())->getValue(),
                'canDelete' => true
            ]
        ]);
    }

    #[Route('/admin/map/portal/{id}/delete', name: 'app_admin_map_portal_delete', methods: ['POST'])]
    public function deletePortal(int $id, Request $request, \App\Repository\MapPortalRepository $mapPortalRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $portal = $mapPortalRepository->find($id);
        if (!$portal) {
            return new JsonResponse(['error' => 'Portal não encontrado.'], 404);
        }

        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete-map-portal-' . $id, $token)) {
            return new JsonResponse(['error' => 'Token CSRF inválido.'], 400);
        }

        $this->entityManager->remove($portal);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/map/{id}/data', name: 'app_map_data', methods: ['GET'])]
    public function getMapData(int $id, CsrfTokenManagerInterface $csrfTokenManager, Packages $assets): JsonResponse
    {
        $map = $this->mapRepository->find($id);
        if (!$map) {
            return new JsonResponse(['error' => 'Mapa não encontrado.'], 404);
        }

        $pokemons = $this->mapPokemonRepository->findBy(['map' => $map], ['createdAt' => 'DESC']);
        $portals = $map->getPortals();

        $pokemonsData = [];
        foreach ($pokemons as $mp) {
            $pokemonsData[] = [
                'id' => $mp->getId(),
                'pokemonId' => $mp->getPokemonId(),
                'pokemonName' => ucfirst($mp->getPokemonName()),
                'latitude' => $mp->getLatitude(),
                'longitude' => $mp->getLongitude(),
                'notes' => $mp->getNotes(),
                'username' => $mp->getUser() ? $mp->getUser()->getUsername() : 'Anônimo',
                'createdAt' => $mp->getCreatedAt()->format('d/m/Y H:i'),
                'canDelete' => $this->isGranted('ROLE_ADMIN')
            ];
        }

        $portalsData = [];
        foreach ($portals as $portal) {
            $portalsData[] = [
                'id' => $portal->getId(),
                'parentMapId' => $map->getId(),
                'targetMapId' => $portal->getTargetMap()->getId(),
                'targetMapName' => $portal->getTargetMap()->getName(),
                'latitude' => $portal->getLatitude(),
                'longitude' => $portal->getLongitude(),
                'createdAt' => $portal->getCreatedAt()->format('d/m/Y H:i'),
                'csrfToken' => $csrfTokenManager->getToken('delete-map-portal-' . $portal->getId())->getValue(),
                'canDelete' => $this->isGranted('ROLE_ADMIN')
            ];
        }

        return new JsonResponse([
            'success' => true,
            'map' => [
                'id' => $map->getId(),
                'name' => $map->getName(),
                'imagePath' => $map->getImagePath(),
                'imageUrl' => $assets->getUrl('uploads/maps/' . $map->getImagePath())
            ],
            'pokemons' => $pokemonsData,
            'portals' => $portalsData
        ]);
    }
}
