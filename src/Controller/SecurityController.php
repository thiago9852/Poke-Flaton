<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    private string $projectDir;

    public function __construct(
        #[Autowire('%kernel.project_dir%')] string $projectDir
    ) {
        $this->projectDir = $projectDir;
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
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_trainer_card');
        }

        $trainersJsonPath = $this->projectDir . '/scratch/trainers.json';
        $trainers = [];
        if (file_exists($trainersJsonPath)) {
            $trainers = json_decode(file_get_contents($trainersJsonPath), true) ?? [];
        }

        // Filtrar apenas avatares iniciais (não bloqueados por conquistas)
        $starterTrainers = array_values(array_filter($trainers, function($trainer) {
            return !isset(\App\Controller\TrainerCardController::AVATAR_REWARDS[$trainer]);
        }));

        $validRegions = ['Kanto', 'Johto', 'Hoenn', 'Sinnoh', 'Unova', 'Kalos'];

        $errors = [];
        $username = '';
        $apelido = '';
        $regional = 'Kanto';

        if ($request->isMethod('POST')) {
            $username = trim($request->request->get('username', ''));
            $password = $request->request->get('password', '');
            $confirmPassword = $request->request->get('confirm_password', '');
            $apelido = trim($request->request->get('apelido', ''));
            $regional = $request->request->get('regional', 'Kanto');

            if (empty($username)) {
                $errors[] = 'O nome de usuário não pode estar vazio.';
            } elseif (strlen($username) < 3 || strlen($username) > 30) {
                $errors[] = 'O nome de usuário deve ter entre 3 e 30 caracteres.';
            } elseif ($userRepository->findOneBy(['username' => $username])) {
                $errors[] = 'Este nome de usuário já está sendo utilizado.';
            }

            if (!empty($apelido) && strlen($apelido) > 30) {
                $errors[] = 'O apelido deve ter no máximo 30 caracteres.';
            }

            if (empty($password)) {
                $errors[] = 'A senha não pode estar vazia.';
            } elseif (strlen($password) < 6) {
                $errors[] = 'A senha deve ter no mínimo 6 caracteres.';
            }

            if ($password !== $confirmPassword) {
                $errors[] = 'As senhas informadas não coincidem.';
            }

            if (!in_array($regional, $validRegions)) {
                $regional = 'Kanto';
            }

            if (empty($errors)) {
                // Avatar padrão baseado na região escolhida
                $defaultAvatars = [
                    'Kanto'  => 'hilbert.png',
                    'Johto'  => 'hilbert.png',
                    'Hoenn'  => 'hilbert.png',
                    'Sinnoh' => 'hilbert.png',
                    'Unova'  => 'hilbert.png',
                    'Kalos'  => 'hilbert.png',
                ];
                $avatar = !empty($starterTrainers) ? $starterTrainers[0] : ($defaultAvatars[$regional] ?? 'hilbert.png');

                $user = new User();
                $user->setUsername($username);
                $user->setApelido($apelido !== '' ? $apelido : null);
                $user->setRegional($regional);
                $user->setAvatar($avatar);
                $user->setPassword(
                    $userPasswordHasher->hashPassword(
                        $user,
                        $password
                    )
                );

                $entityManager->persist($user);
                $entityManager->flush();

                $this->addFlash('success', 'Cadastro realizado com sucesso! Faça seu login para continuar.');

                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('security/register.html.twig', [
            'errors'       => $errors,
            'username'     => $username,
            'apelido'      => $apelido,
            'regional'     => $regional,
            'validRegions' => $validRegions,
        ]);
    }
}
