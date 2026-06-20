<?php

namespace App\Command;

use App\Service\TrainerProfileService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:db-seed',
    description: 'Popula o banco de dados com os títulos padrão, avatares e sincroniza templates/avatares a partir do GitHub.',
)]
class AppDbSeedCommand extends Command
{
    private TrainerProfileService $trainerProfileService;

    public function __construct(TrainerProfileService $trainerProfileService)
    {
        parent::__construct();
        $this->trainerProfileService = $trainerProfileService;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Iniciando Seeding e Inicialização do Banco de Dados');

        $io->section('Inicializando Títulos padrão...');
        $this->trainerProfileService->initializeDatabaseAndTitles();
        $io->success('Títulos inicializados com sucesso!');

        $io->section('Inicializando Avatares padrão...');
        $this->trainerProfileService->initializeDatabaseAndAvatars();
        $io->success('Avatares iniciais inseridos com sucesso!');

        $io->section('Sincronizando Avatares a partir da API do GitHub...');
        try {
            $avatarResult = $this->trainerProfileService->syncAvatarsFromApi();
            $io->success(sprintf('Sincronização de avatares concluída! %d novos inseridos.', $avatarResult['inserted']));
        } catch (\Exception $e) {
            $io->warning('Não foi possível sincronizar os avatares do GitHub: ' . $e->getMessage());
        }

        $io->section('Sincronizando Templates a partir da API do GitHub...');
        try {
            $templateResult = $this->trainerProfileService->syncTemplatesFromApi();
            $io->success(sprintf('Sincronização de templates concluída! %d novos inseridos.', $templateResult['inserted']));
        } catch (\Exception $e) {
            $io->warning('Não foi possível sincronizar os templates do GitHub: ' . $e->getMessage());
        }

        $io->success('Processo de seeding de banco de dados concluído com sucesso!');

        return Command::SUCCESS;
    }
}
