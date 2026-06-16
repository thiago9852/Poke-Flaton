<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260616221555 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE avatar (id INT AUTO_INCREMENT NOT NULL, filename VARCHAR(255) NOT NULL, type VARCHAR(50) NOT NULL, requirement VARCHAR(255) DEFAULT NULL, req_medal VARCHAR(255) DEFAULT NULL, req_tier VARCHAR(255) DEFAULT NULL, req_gold_count INT DEFAULT NULL, req_rank_type VARCHAR(50) DEFAULT NULL, req_rank_pos INT DEFAULT NULL, is_default TINYINT DEFAULT 0 NOT NULL, UNIQUE INDEX UNIQ_1677722F3C0BE965 (filename), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE card_template (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, image VARCHAR(255) NOT NULL, requirement VARCHAR(255) NOT NULL, req_medal VARCHAR(255) DEFAULT NULL, req_tier VARCHAR(255) DEFAULT NULL, req_gold_count INT DEFAULT NULL, req_rank_type VARCHAR(50) DEFAULT NULL, req_rank_pos INT DEFAULT NULL, is_default TINYINT DEFAULT 0 NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE evolution_rule (id INT AUTO_INCREMENT NOT NULL, base_pokemon VARCHAR(100) NOT NULL, evolved_pokemon VARCHAR(100) NOT NULL, method VARCHAR(100) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE moveset (id INT AUTO_INCREMENT NOT NULL, pokemon_name VARCHAR(100) NOT NULL, pokemon_id INT NOT NULL, type VARCHAR(50) NOT NULL, moves JSON NOT NULL, ability VARCHAR(100) NOT NULL, held_item VARCHAR(100) NOT NULL, nature VARCHAR(100) NOT NULL, votes INT NOT NULL, author VARCHAR(100) DEFAULT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE pokemon_access (id INT AUTO_INCREMENT NOT NULL, pokemon_name VARCHAR(100) NOT NULL, pokemon_id INT NOT NULL, views INT NOT NULL, last_accessed_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_6A805700628A0045 (pokemon_name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE pokemon_game_score (id INT AUTO_INCREMENT NOT NULL, user_token VARCHAR(255) DEFAULT NULL, username VARCHAR(255) NOT NULL, attempts INT NOT NULL, won TINYINT NOT NULL, created_at DATETIME NOT NULL, game_date DATE NOT NULL, user_id INT DEFAULT NULL, INDEX IDX_D29622C9A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE pokemon_location (id INT AUTO_INCREMENT NOT NULL, pokemon_name VARCHAR(100) NOT NULL, location_name VARCHAR(150) NOT NULL, is_approved TINYINT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE pokemon_suggestion (id INT AUTO_INCREMENT NOT NULL, pokemon_name VARCHAR(100) NOT NULL, type VARCHAR(50) NOT NULL, value VARCHAR(100) NOT NULL, votes INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE title (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, ribbon VARCHAR(255) NOT NULL, requirement VARCHAR(255) NOT NULL, req_medal VARCHAR(255) DEFAULT NULL, req_tier VARCHAR(255) DEFAULT NULL, req_gold_count INT DEFAULT NULL, req_rank_type VARCHAR(50) DEFAULT NULL, req_rank_pos INT DEFAULT NULL, is_default TINYINT DEFAULT 0 NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, avatar VARCHAR(255) DEFAULT NULL, unlocked_tms JSON NOT NULL, caught_pokemon JSON NOT NULL, following JSON NOT NULL, vivillon_patterns JSON NOT NULL, showcase_medals JSON NOT NULL, title VARCHAR(255) DEFAULT NULL, card_template VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, apelido VARCHAR(255) DEFAULT NULL, regional VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_8D93D649F85E0677 (username), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE pokemon_game_score ADD CONSTRAINT FK_D29622C9A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pokemon_game_score DROP FOREIGN KEY FK_D29622C9A76ED395');
        $this->addSql('DROP TABLE avatar');
        $this->addSql('DROP TABLE card_template');
        $this->addSql('DROP TABLE evolution_rule');
        $this->addSql('DROP TABLE moveset');
        $this->addSql('DROP TABLE pokemon_access');
        $this->addSql('DROP TABLE pokemon_game_score');
        $this->addSql('DROP TABLE pokemon_location');
        $this->addSql('DROP TABLE pokemon_suggestion');
        $this->addSql('DROP TABLE title');
        $this->addSql('DROP TABLE `user`');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
