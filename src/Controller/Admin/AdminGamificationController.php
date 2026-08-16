<?php

namespace App\Controller\Admin;

use App\Form\CardTemplateRulesType;
use App\Repository\CardTemplateRepository;
use App\Service\TrainerProfileService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminGamificationController extends AbstractController
{
    public function __construct(
        private readonly CardTemplateRepository $cardTemplateRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly TrainerProfileService $trainerProfileService,
    ) {
    }

    #[Route('/admin/templates/sync', name: 'app_admin_templates_sync', methods: ['POST'])]
    public function syncTemplates(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $result = $this->trainerProfileService->syncTemplatesFromApi();

        $this->addFlash('success', sprintf('Sincronização concluída! %d novos templates inseridos (Total indexado: %d).', $result['inserted'], $result['total']));

        return $this->redirectToRoute('app_admin', ['tab' => 'users', '_fragment' => 'card-templates-section']);
    }

    #[Route('/admin/templates/reset', name: 'app_admin_templates_reset', methods: ['POST'])]
    public function resetTemplates(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $result = $this->trainerProfileService->resetAndSyncTemplates();

        $this->addFlash('success', sprintf('Banco de templates resetado e sincronizado com sucesso! Total indexado: %d.', $result['total']));

        return $this->redirectToRoute('app_admin', ['tab' => 'users', '_fragment' => 'card-templates-section']);
    }

    #[Route('/admin/card-template/{id}/delete', name: 'app_admin_card_template_delete', methods: ['POST'])]
    public function deleteCardTemplate(int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $template = $this->cardTemplateRepository->find($id);
        if (!$template) {
            $this->addFlash('error', 'Plano de fundo não encontrado.');

            return $this->redirectToRoute('app_admin');
        }

        if ($template->isDefault()) {
            $this->addFlash('error', 'Você não pode excluir o plano de fundo padrão.');

            return $this->redirectToRoute('app_admin');
        }

        $this->entityManager->remove($template);
        $this->entityManager->flush();

        $this->addFlash('success', 'Plano de fundo excluído com sucesso!');

        return $this->redirectToRoute('app_admin', ['tab' => 'users', '_fragment' => 'card-templates-section']);
    }

    #[Route('/admin/template/{id}/update', name: 'app_admin_template_update', methods: ['POST'])]
    public function updateTemplateRules(int $id, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $template = $this->cardTemplateRepository->find($id);
        if (!$template) {
            $this->addFlash('error', 'Plano de fundo não encontrado.');

            return $this->redirectToRoute('app_admin');
        }

        $form = $this->createForm(CardTemplateRulesType::class, $template);
        $data = $request->request->all();
        if (!isset($data['isDefault'])) {
            $data['isDefault'] = false;
        }
        $form->submit($data);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($template->getRequirement() === '' || $template->getRequirement() === null) {
                $template->setRequirement('Bloqueado por padrão');
            }

            $this->entityManager->persist($template);
            $this->entityManager->flush();
            $this->addFlash('success', 'Regras do plano de fundo atualizadas com sucesso!');
        } else {
            $this->addFlash('error', 'Erro ao atualizar as regras do plano de fundo.');
        }

        return $this->redirectToRoute('app_admin', ['tab' => 'users', '_fragment' => 'card-templates-section']);
    }

    #[Route('/admin/avatars/sync', name: 'app_admin_avatars_sync', methods: ['POST'])]
    public function syncAvatars(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $result = $this->trainerProfileService->syncAvatarsFromApi();

        $this->addFlash('success', sprintf('Sincronização concluída! %d novos avatares inseridos (Total indexado: %d).', $result['inserted'], $result['total']));

        return $this->redirectToRoute('app_admin', ['tab' => 'users', '_fragment' => 'avatars-section']);
    }

    #[Route('/admin/avatars/reset', name: 'app_admin_avatars_reset', methods: ['POST'])]
    public function resetAvatars(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $result = $this->trainerProfileService->resetAndSyncAvatars();

        $this->addFlash('success', sprintf('Banco de avatares resetado e sincronizado com sucesso! total indexado: %d.', $result['total']));

        return $this->redirectToRoute('app_admin', ['tab' => 'users', '_fragment' => 'avatars-section']);
    }
}
