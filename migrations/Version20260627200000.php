<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration to create map and map_pokemon tables for custom map images.
 */
final class Version20260627200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create map and map_pokemon tables with custom image map support';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE map (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(150) NOT NULL, image_path VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE map_pokemon (id INT AUTO_INCREMENT NOT NULL, map_id INT NOT NULL, user_id INT DEFAULT NULL, pokemon_name VARCHAR(100) NOT NULL, pokemon_id INT NOT NULL, latitude DOUBLE PRECISION NOT NULL, longitude DOUBLE PRECISION NOT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_9DAE0FCE53C55F64 (map_id), INDEX IDX_9DAE0FCEA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE map_pokemon ADD CONSTRAINT FK_9DAE0FCE53C55F64 FOREIGN KEY (map_id) REFERENCES map (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE map_pokemon ADD CONSTRAINT FK_9DAE0FCEA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE map_pokemon DROP FOREIGN KEY FK_9DAE0FCE53C55F64');
        $this->addSql('ALTER TABLE map_pokemon DROP FOREIGN KEY FK_9DAE0FCEA76ED395');
        $this->addSql('DROP TABLE map');
        $this->addSql('DROP TABLE map_pokemon');
    }
}
