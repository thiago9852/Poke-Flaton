<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260816184550 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop orphaned tables (map, map_pokemon, map_portal, pokemon_game_score) left over from removed features.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE map_pokemon DROP FOREIGN KEY `FK_9DAE0FCE53C55F64`');
        $this->addSql('ALTER TABLE map_pokemon DROP FOREIGN KEY `FK_9DAE0FCEA76ED395`');
        $this->addSql('ALTER TABLE map_portal DROP FOREIGN KEY `FK_A52A4A83158309A3`');
        $this->addSql('ALTER TABLE map_portal DROP FOREIGN KEY `FK_A52A4A836DDF4B7F`');
        $this->addSql('ALTER TABLE pokemon_game_score DROP FOREIGN KEY `FK_D29622C9A76ED395`');
        $this->addSql('DROP TABLE map');
        $this->addSql('DROP TABLE map_pokemon');
        $this->addSql('DROP TABLE map_portal');
        $this->addSql('DROP TABLE pokemon_game_score');
        $this->addSql('ALTER TABLE tier_list RENAME INDEX user_id TO IDX_C4498D71A76ED395');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE map (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(150) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME NOT NULL, image_path VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, is_submap TINYINT DEFAULT 0 NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE map_pokemon (id INT AUTO_INCREMENT NOT NULL, map_id INT NOT NULL, user_id INT DEFAULT NULL, pokemon_name VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, pokemon_id INT NOT NULL, latitude DOUBLE PRECISION NOT NULL, longitude DOUBLE PRECISION NOT NULL, notes LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME NOT NULL, INDEX IDX_4DF768AF53C55F64 (map_id), INDEX IDX_4DF768AFA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE map_portal (id INT AUTO_INCREMENT NOT NULL, parent_map_id INT NOT NULL, target_map_id INT NOT NULL, latitude DOUBLE PRECISION NOT NULL, longitude DOUBLE PRECISION NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_A52A4A83158309A3 (target_map_id), INDEX IDX_A52A4A836DDF4B7F (parent_map_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE pokemon_game_score (id INT AUTO_INCREMENT NOT NULL, user_token VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, username VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, attempts INT NOT NULL, won TINYINT NOT NULL, created_at DATETIME NOT NULL, game_date DATE NOT NULL, user_id INT DEFAULT NULL, INDEX IDX_D29622C9A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE map_pokemon ADD CONSTRAINT `FK_9DAE0FCE53C55F64` FOREIGN KEY (map_id) REFERENCES map (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE map_pokemon ADD CONSTRAINT `FK_9DAE0FCEA76ED395` FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('ALTER TABLE map_portal ADD CONSTRAINT `FK_A52A4A83158309A3` FOREIGN KEY (target_map_id) REFERENCES map (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE map_portal ADD CONSTRAINT `FK_A52A4A836DDF4B7F` FOREIGN KEY (parent_map_id) REFERENCES map (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE pokemon_game_score ADD CONSTRAINT `FK_D29622C9A76ED395` FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('ALTER TABLE tier_list RENAME INDEX idx_c4498d71a76ed395 TO user_id');
    }
}
