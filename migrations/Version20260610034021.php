<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260610034021 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE pokemon_access (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, pokemon_name VARCHAR(100) NOT NULL, pokemon_id INTEGER NOT NULL, views INTEGER NOT NULL, last_accessed_at DATETIME DEFAULT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6A805700628A0045 ON pokemon_access (pokemon_name)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__user AS SELECT id, username, roles, password, avatar, unlocked_tms, created_at, caught_pokemon, following, vivillon_patterns, showcase_medals, title, apelido, regional FROM user');
        $this->addSql('DROP TABLE user');
        $this->addSql('CREATE TABLE user (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, username VARCHAR(180) NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL, avatar VARCHAR(255) DEFAULT NULL, unlocked_tms CLOB NOT NULL, created_at DATETIME NOT NULL, caught_pokemon CLOB NOT NULL, following CLOB NOT NULL, vivillon_patterns CLOB NOT NULL, showcase_medals CLOB DEFAULT \'[]\' NOT NULL, title VARCHAR(255) DEFAULT NULL, apelido VARCHAR(255) DEFAULT NULL, regional VARCHAR(255) NOT NULL)');
        $this->addSql('INSERT INTO user (id, username, roles, password, avatar, unlocked_tms, created_at, caught_pokemon, following, vivillon_patterns, showcase_medals, title, apelido, regional) SELECT id, username, roles, password, avatar, unlocked_tms, created_at, caught_pokemon, following, vivillon_patterns, showcase_medals, title, apelido, regional FROM __temp__user');
        $this->addSql('DROP TABLE __temp__user');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649F85E0677 ON user (username)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE pokemon_access');
        $this->addSql('CREATE TEMPORARY TABLE __temp__user AS SELECT id, username, roles, password, avatar, unlocked_tms, caught_pokemon, following, vivillon_patterns, showcase_medals, title, created_at, apelido, regional FROM "user"');
        $this->addSql('DROP TABLE "user"');
        $this->addSql('CREATE TABLE "user" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, username VARCHAR(180) NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL, avatar VARCHAR(255) DEFAULT NULL, unlocked_tms CLOB NOT NULL, caught_pokemon CLOB NOT NULL, following CLOB NOT NULL, vivillon_patterns CLOB NOT NULL, showcase_medals CLOB DEFAULT \'[]\' NOT NULL, title VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, apelido VARCHAR(255) DEFAULT NULL, regional VARCHAR(255) DEFAULT \'[Kanto]\' NOT NULL)');
        $this->addSql('INSERT INTO "user" (id, username, roles, password, avatar, unlocked_tms, caught_pokemon, following, vivillon_patterns, showcase_medals, title, created_at, apelido, regional) SELECT id, username, roles, password, avatar, unlocked_tms, caught_pokemon, following, vivillon_patterns, showcase_medals, title, created_at, apelido, regional FROM __temp__user');
        $this->addSql('DROP TABLE __temp__user');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649F85E0677 ON "user" (username)');
    }
}
