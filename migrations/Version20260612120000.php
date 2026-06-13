<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260612120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add is_approved column to pokemon_location';
    }

    public function up(Schema $schema): void
    {
        // Add the is_approved column with a default value of false (0 in SQLite)
        $this->addSql('ALTER TABLE pokemon_location ADD COLUMN is_approved BOOLEAN DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // SQLite doesn't support dropping columns easily. We must recreate the table structure without it.
        $this->addSql('CREATE TEMPORARY TABLE __temp__pokemon_location AS SELECT id, pokemon_name, location_name, created_at FROM pokemon_location');
        $this->addSql('DROP TABLE pokemon_location');
        $this->addSql('CREATE TABLE pokemon_location (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, pokemon_name VARCHAR(100) NOT NULL, location_name VARCHAR(150) NOT NULL, created_at DATETIME NOT NULL)');
        $this->addSql('INSERT INTO pokemon_location (id, pokemon_name, location_name, created_at) SELECT id, pokemon_name, location_name, created_at FROM __temp__pokemon_location');
        $this->addSql('DROP TABLE __temp__pokemon_location');
    }
}
