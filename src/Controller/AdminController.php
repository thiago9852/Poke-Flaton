<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminController extends AbstractController
{
    #[Route('/admin', name: 'app_admin', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->redirectToRoute('app_admin_pokemon');
    }

    #[Route('/admin/clear-cache', name: 'app_admin_clear_cache', methods: ['POST'])]
    public function clearCache(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Validar token CSRF
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('admin-clear-cache', $token)) {
            $this->addFlash('error', 'Token de segurança inválido.');

            return $this->redirectToRoute('app_admin');
        }

        try {
            $cacheDir = $this->getParameter('kernel.cache_dir');

            // Usamos a classe Filesystem do Symfony para limpar a pasta de cache recursivamente
            $fs = new Filesystem();
            if ($fs->exists($cacheDir)) {
                $fs->remove($cacheDir);
            }

            $this->addFlash('success', 'Cache de produção limpo com sucesso! Os templates e arquivos foram atualizados.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Falha ao limpar o cache: '.$e->getMessage());
        }

        return $this->redirectToRoute('app_admin');
    }
}
