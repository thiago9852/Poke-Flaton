<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260816191729 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove gamification systems: titles, medals and trainer ranking (progression-unlock rules on avatar/card_template are dropped too, since nothing enforces them anymore).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE title');
        $this->addSql('ALTER TABLE avatar DROP req_medal, DROP req_tier, DROP req_gold_count, DROP req_rank_type, DROP req_rank_pos');
        $this->addSql('ALTER TABLE card_template DROP req_medal, DROP req_tier, DROP req_gold_count, DROP req_rank_type, DROP req_rank_pos');
        $this->addSql('ALTER TABLE user DROP vivillon_patterns, DROP showcase_medals, DROP title');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE title (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, ribbon VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, requirement VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, req_medal VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, req_tier VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, req_gold_count INT DEFAULT NULL, req_rank_type VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, req_rank_pos INT DEFAULT NULL, is_default TINYINT DEFAULT 0 NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE avatar ADD req_medal VARCHAR(255) DEFAULT NULL, ADD req_tier VARCHAR(255) DEFAULT NULL, ADD req_gold_count INT DEFAULT NULL, ADD req_rank_type VARCHAR(50) DEFAULT NULL, ADD req_rank_pos INT DEFAULT NULL');
        $this->addSql('ALTER TABLE card_template ADD req_medal VARCHAR(255) DEFAULT NULL, ADD req_tier VARCHAR(255) DEFAULT NULL, ADD req_gold_count INT DEFAULT NULL, ADD req_rank_type VARCHAR(50) DEFAULT NULL, ADD req_rank_pos INT DEFAULT NULL');
        $this->addSql('ALTER TABLE `user` ADD vivillon_patterns JSON NOT NULL, ADD showcase_medals JSON NOT NULL, ADD title VARCHAR(255) DEFAULT NULL');
    }
}
