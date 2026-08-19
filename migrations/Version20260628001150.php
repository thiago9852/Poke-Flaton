<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajustada para evitar DROP de colunas inexistentes.
 */
final class Version20260628001150 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Atualiza tabela map e índices de map_pokemon';
    }

    public function up(Schema $schema): void
    {
        // Ajuste em created_at e garantia de image_path
        $this->addSql('ALTER TABLE map CHANGE created_at created_at DATETIME NOT NULL');

        // Ajuste em created_at de map_pokemon
        $this->addSql('ALTER TABLE map_pokemon CHANGE created_at created_at DATETIME NOT NULL');

        // Renomear índices
        $this->addSql('ALTER TABLE map_pokemon RENAME INDEX idx_9dae0fce53c55f64 TO IDX_4DF768AF53C55F64');
        $this->addSql('ALTER TABLE map_pokemon RENAME INDEX idx_9dae0fcea76ed395 TO IDX_4DF768AFA76ED395');
    }

    public function down(Schema $schema): void
    {
        // Reverter ajustes
        $this->addSql('ALTER TABLE map CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');

        $this->addSql('ALTER TABLE map_pokemon CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');

        // Restaurar nomes originais dos índices
        $this->addSql('ALTER TABLE map_pokemon RENAME INDEX idx_4df768af53c55f64 TO IDX_9DAE0FCE53C55F64');
        $this->addSql('ALTER TABLE map_pokemon RENAME INDEX idx_4df768afa76ed395 TO IDX_9DAE0FCEA76ED395');
    }
}
