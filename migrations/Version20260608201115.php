<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260608201115 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user ADD COLUMN apelido VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD COLUMN regional VARCHAR(255) NOT NULL DEFAULT [Kanto]');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__user AS SELECT id, username, roles, password, avatar, unlocked_tms, caught_pokemon, following, vivillon_patterns, showcase_medals, title, created_at FROM "user"');
        $this->addSql('DROP TABLE "user"');
        $this->addSql('CREATE TABLE "user" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, username VARCHAR(180) NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL, avatar VARCHAR(255) DEFAULT NULL, unlocked_tms CLOB NOT NULL, caught_pokemon CLOB NOT NULL, following CLOB NOT NULL, vivillon_patterns CLOB NOT NULL, showcase_medals CLOB DEFAULT \'[]\' NOT NULL, title VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL)');
        $this->addSql('INSERT INTO "user" (id, username, roles, password, avatar, unlocked_tms, caught_pokemon, following, vivillon_patterns, showcase_medals, title, created_at) SELECT id, username, roles, password, avatar, unlocked_tms, caught_pokemon, following, vivillon_patterns, showcase_medals, title, created_at FROM __temp__user');
        $this->addSql('DROP TABLE __temp__user');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649F85E0677 ON "user" (username)');
    }
}
