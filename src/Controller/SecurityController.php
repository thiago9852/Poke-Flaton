<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SecurityController extends AbstractController
{
    private function getGoogleClientId(): ?string
    {
        return $_ENV['GOOGLE_CLIENT_ID'] ?? $_SERVER['GOOGLE_CLIENT_ID'] ?? null;
    }

    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_trainer_card');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'google_client_id' => $this->getGoogleClientId(),
        ]);
    }

    /**
     * Valida o ID token do Google no endpoint oficial de verificação, que checa a assinatura
     * criptográfica no lado do Google. Evita decodificar e confiar no payload sem verificação
     * (qualquer JWT malformado poderia ser forjado com um e-mail arbitrário).
     *
     * @throws \Exception se o token for inválido, expirado, ou não pertencer a este client ID
     */
    private function verifyGoogleIdToken(string $credential, HttpClientInterface $httpClient): array
    {
        $clientId = $this->getGoogleClientId();
        if (!$clientId) {
            throw new \Exception('Login com Google não está configurado no servidor.');
        }

        $response = $httpClient->request('GET', 'https://oauth2.googleapis.com/tokeninfo', [
            'query' => ['id_token' => $credential],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \Exception('Token inválido ou expirado.');
        }

        $payload = $response->toArray(false);

        if (!in_array($payload['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'], true)) {
            throw new \Exception('Emissor do token inválido.');
        }

        if (($payload['aud'] ?? null) !== $clientId) {
            throw new \Exception('Token não pertence a este aplicativo.');
        }

        if (($payload['email_verified'] ?? 'false') !== 'true') {
            throw new \Exception('E-mail do Google não verificado.');
        }

        if (empty($payload['email'])) {
            throw new \Exception('E-mail ausente no token.');
        }

        return $payload;
    }

    #[Route('/login/google/callback', name: 'app_google_callback', methods: ['POST'])]
    public function googleCallback(
        Request $request,
        Security $security,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        HttpClientInterface $httpClient,
    ): Response {
        $credential = $request->request->get('credential');
        if (!$credential) {
            $this->addFlash('error', 'Token de login inválido do Google.');

            return $this->redirectToRoute('app_login');
        }

        try {
            $payload = $this->verifyGoogleIdToken($credential, $httpClient);

            $email = $payload['email'];
            $name = $payload['name'] ?? explode('@', $email)[0];

            $user = $userRepository->findOneBy(['username' => $email]);
            if (!$user) {
                $user = new User();
                $user->setUsername($email);
                $user->setApelido($name);
                $user->setRoles(['ROLE_USER']);
                $user->setAvatar('trainer:unknown.png');
                $user->setPassword('');
                $user->setRegional('kanto');

                $entityManager->persist($user);
                $entityManager->flush();
            }

            $security->login($user, 'form_login');

            return $this->redirectToRoute('app_home');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erro ao realizar login com o Google: '.$e->getMessage());

            return $this->redirectToRoute('app_login');
        }
    }

    #[Route('/login/mock', name: 'app_mock_login', methods: ['POST'])]
    public function mockLogin(
        Security $security,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $mockUsername = 'portfolio.visitor';
        $user = $userRepository->findOneBy(['username' => $mockUsername]);
        if (!$user) {
            $user = new User();
            $user->setUsername($mockUsername);
            $user->setApelido('Portfolio Visitor');
            $user->setRoles(['ROLE_USER']);
            $user->setAvatar('trainer:unknown.png');
            $user->setPassword('');
            $user->setRegional('kanto');

            $entityManager->persist($user);
            $entityManager->flush();
        }

        $security->login($user, 'form_login');

        return $this->redirectToRoute('app_home');
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('Interceptado pela chave de loggout do firewall');
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_trainer_card');
        }

        $user = new User();
        $form = $this->createForm(RegistrationType::class, $user);
        $form->handleRequest($request);

        $errors = [];

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $user->setAvatar('trainer:unknown.png');
                $user->setRoles(['ROLE_USER']);

                // Hash password
                $hashedPassword = $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('password')->getData()
                );
                $user->setPassword($hashedPassword);

                $entityManager->persist($user);
                $entityManager->flush();

                $this->addFlash('success', 'Cadastro realizado com sucesso! Faça seu login para continuar.');

                return $this->redirectToRoute('app_login');
            }
            foreach ($form->getErrors(true) as $error) {
                $errors[] = $error->getMessage();
            }
        }

        return $this->render('security/register.html.twig', [
            'form' => $form->createView(),
            'errors' => $errors,
        ]);
    }
}
