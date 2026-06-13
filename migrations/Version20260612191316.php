<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260612191316 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE card_template (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, image VARCHAR(255) NOT NULL, requirement VARCHAR(255) NOT NULL, req_medal VARCHAR(255) DEFAULT NULL, req_tier VARCHAR(255) DEFAULT NULL, req_gold_count INTEGER DEFAULT NULL, is_default BOOLEAN DEFAULT 0 NOT NULL)');
        $this->addSql('ALTER TABLE user ADD COLUMN card_template VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE card_template');
        $this->addSql('CREATE TEMPORARY TABLE __temp__user AS SELECT id, username, roles, password, avatar, unlocked_tms, caught_pokemon, following, vivillon_patterns, showcase_medals, title, created_at, apelido, regional FROM "user"');
        $this->addSql('DROP TABLE "user"');
        $this->addSql('CREATE TABLE "user" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, username VARCHAR(180) NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL, avatar VARCHAR(255) DEFAULT NULL, unlocked_tms CLOB NOT NULL, caught_pokemon CLOB NOT NULL, following CLOB NOT NULL, vivillon_patterns CLOB NOT NULL, showcase_medals CLOB DEFAULT \'[]\' NOT NULL, title VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, apelido VARCHAR(255) DEFAULT NULL, regional VARCHAR(255) NOT NULL)');
        $this->addSql('INSERT INTO "user" (id, username, roles, password, avatar, unlocked_tms, caught_pokemon, following, vivillon_patterns, showcase_medals, title, created_at, apelido, regional) SELECT id, username, roles, password, avatar, unlocked_tms, caught_pokemon, following, vivillon_patterns, showcase_medals, title, created_at, apelido, regional FROM __temp__user');
        $this->addSql('DROP TABLE __temp__user');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649F85E0677 ON "user" (username)');
    }
}
