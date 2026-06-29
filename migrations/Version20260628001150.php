<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260628001150 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE map ADD image_path VARCHAR(255) NOT NULL, DROP center_latitude, DROP center_longitude, DROP zoom, CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE map_pokemon CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE map_pokemon RENAME INDEX idx_9dae0fce53c55f64 TO IDX_4DF768AF53C55F64');
        $this->addSql('ALTER TABLE map_pokemon RENAME INDEX idx_9dae0fcea76ed395 TO IDX_4DF768AFA76ED395');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE map ADD center_latitude DOUBLE PRECISION NOT NULL, ADD center_longitude DOUBLE PRECISION NOT NULL, ADD zoom INT DEFAULT 12 NOT NULL, DROP image_path, CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE map_pokemon CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE map_pokemon RENAME INDEX idx_4df768af53c55f64 TO IDX_9DAE0FCE53C55F64');
        $this->addSql('ALTER TABLE map_pokemon RENAME INDEX idx_4df768afa76ed395 TO IDX_9DAE0FCEA76ED395');
    }
}
