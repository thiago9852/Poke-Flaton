<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260604131324 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE pokemon_suggestion (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, pokemon_name VARCHAR(100) NOT NULL, type VARCHAR(50) NOT NULL, value VARCHAR(100) NOT NULL, votes INTEGER NOT NULL)');
        $this->addSql('ALTER TABLE moveset ADD COLUMN author VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE pokemon_suggestion');
        $this->addSql('CREATE TEMPORARY TABLE __temp__moveset AS SELECT id, pokemon_name, pokemon_id, type, moves, ability, held_item, nature, description, votes, created_at FROM moveset');
        $this->addSql('DROP TABLE moveset');
        $this->addSql('CREATE TABLE moveset (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, pokemon_name VARCHAR(100) NOT NULL, pokemon_id INTEGER NOT NULL, type VARCHAR(50) NOT NULL, moves CLOB NOT NULL, ability VARCHAR(100) NOT NULL, held_item VARCHAR(100) NOT NULL, nature VARCHAR(100) NOT NULL, description CLOB DEFAULT NULL, votes INTEGER NOT NULL, created_at DATETIME NOT NULL)');
        $this->addSql('INSERT INTO moveset (id, pokemon_name, pokemon_id, type, moves, ability, held_item, nature, description, votes, created_at) SELECT id, pokemon_name, pokemon_id, type, moves, ability, held_item, nature, description, votes, created_at FROM __temp__moveset');
        $this->addSql('DROP TABLE __temp__moveset');
    }
}
