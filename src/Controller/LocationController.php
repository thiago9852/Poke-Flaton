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

            $isAdmin = $this->isGranted('ROLE_ADMIN');
            $pokemonLocation->setIsApproved($isAdmin);

            $this->entityManager->persist($pokemonLocation);
            $this->entityManager->flush();

            if ($isAdmin) {
                $this->addFlash('success', 'Localização adicionada com sucesso!');
            } else {
                $this->addFlash('success', 'Sugestão de localização enviada com sucesso! Ela será validada por um administrador.');
            }
        }

        return $this->redirectToRoute('app_pokemon_detail', ['name' => $pokemon['name']]);
    }

    #[Route('/admin/location/{id}/approve', name: 'app_location_approve', methods: ['POST'])]
    public function approve(int $id, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $location = $this->entityManager->getRepository(PokemonLocation::class)->find($id);
        if (!$location) {
            $this->addFlash('error', 'Localização não encontrada.');
            return $this->redirectToRoute('app_home');
        }

        $location->setIsApproved(true);
        $this->entityManager->flush();

        $this->addFlash('success', 'Sugestão de localização aprovada com sucesso!');

        if ($request->query->get('redirect') === 'admin') {
            return $this->redirectToRoute('app_admin_pokemon', ['_fragment' => 'moderation-section']);
        }

        return $this->redirectToRoute('app_pokemon_detail', ['name' => $location->getPokemonName()]);
    }

    #[Route('/admin/location/{id}/delete', name: 'app_location_delete', methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $location = $this->entityManager->getRepository(PokemonLocation::class)->find($id);
        if (!$location) {
            $this->addFlash('error', 'Localização não encontrada.');
            return $this->redirectToRoute('app_home');
        }

        $pokemonName = $location->getPokemonName();
        $this->entityManager->remove($location);
        $this->entityManager->flush();

        $this->addFlash('success', 'Sugestão de localização removida/rejeitada com sucesso!');

        if ($request->query->get('redirect') === 'admin') {
            return $this->redirectToRoute('app_admin_pokemon', ['_fragment' => 'moderation-section']);
        }

        return $this->redirectToRoute('app_pokemon_detail', ['name' => $pokemonName]);
    }

    #[Route('/admin/location/bulk', name: 'app_location_bulk', methods: ['POST'])]
    public function bulk(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $ids = $request->request->all('ids');
        $action = $request->request->get('action');

        if (empty($ids) || !is_array($ids)) {
            $this->addFlash('error', 'Nenhuma localização selecionada.');
            return $this->redirectToRoute('app_admin_pokemon', ['tab' => 'users', '_fragment' => 'moderation-section']);
        }

        $repo = $this->entityManager->getRepository(PokemonLocation::class);
        $count = 0;

        foreach ($ids as $id) {
            $location = $repo->find((int)$id);
            if ($location) {
                if ($action === 'approve') {
                    $location->setIsApproved(true);
                } elseif ($action === 'delete') {
                    $this->entityManager->remove($location);
                }
                $count++;
            }
        }

        $this->entityManager->flush();

        if ($action === 'approve') {
            $this->addFlash('success', sprintf('%d localizações aprovadas com sucesso!', $count));
        } elseif ($action === 'delete') {
            $this->addFlash('success', sprintf('%d localizações removidas com sucesso!', $count));
        }

        return $this->redirectToRoute('app_admin_pokemon', ['tab' => 'users', '_fragment' => 'moderation-section']);
    }

    #[Route('/admin/location/import-official', name: 'app_location_import_official', methods: ['POST'])]
    public function importOfficial(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $pokemonName = trim($request->request->get('pokemonName', ''));
        $locations = $request->request->all('locations');

        if (empty($pokemonName)) {
            $this->addFlash('error', 'Nome do Pokémon não especificado.');
            return $this->redirectToRoute('app_admin_pokemon', ['tab' => 'games', '_fragment' => 'moderation-section']);
        }

        if (empty($locations) || !is_array($locations)) {
            $this->addFlash('error', 'Nenhuma localização oficial selecionada para importação.');
            return $this->redirectToRoute('app_admin_pokemon', ['tab' => 'games', 'pokemon' => $pokemonName, '_fragment' => 'moderation-section']);
        }

        $count = 0;
        foreach ($locations as $locName) {
            $locName = trim($locName);
            if (!empty($locName)) {
                $exists = $this->entityManager->getRepository(PokemonLocation::class)->findOneBy([
                    'pokemonName' => strtolower($pokemonName),
                    'locationName' => $locName
                ]);

                if (!$exists) {
                    $pokemonLocation = new PokemonLocation();
                    $pokemonLocation->setPokemonName(strtolower($pokemonName));
                    $pokemonLocation->setLocationName($locName);
                    $pokemonLocation->setIsApproved(true);

                    $this->entityManager->persist($pokemonLocation);
                    $count++;
                }
            }
        }

        $this->entityManager->flush();

        if ($count > 0) {
            $this->addFlash('success', sprintf('%d localizações oficiais importadas com sucesso para %s!', $count, ucfirst($pokemonName)));
        } else {
            $this->addFlash('info', 'As localizações selecionadas já estavam cadastradas.');
        }

        return $this->redirectToRoute('app_admin_pokemon', ['tab' => 'games', 'pokemon' => $pokemonName, '_fragment' => 'moderation-section']);
    }
}
