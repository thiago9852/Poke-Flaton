<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LocaleController extends AbstractController
{
    #[Route('/change-locale/{locale}', name: 'app_change_locale')]
    public function changeLocale(string $locale, Request $request): Response
    {
        $supportedLocales = ['pt_BR', 'en'];
        $targetLocale = $locale;
        
        if ($locale === 'ptbr' || $locale === 'pt-BR') {
            $targetLocale = 'pt_BR';
        }

        if (in_array($targetLocale, $supportedLocales, true)) {
            $request->getSession()->set('_locale', $targetLocale);
        }

        $referer = $request->headers->get('referer');
        return $this->redirect($referer ?: $this->generateUrl('app_home'));
    }
}
