<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260614221256 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE avatar ADD req_rank_type VARCHAR(50) DEFAULT NULL, ADD req_rank_pos INT DEFAULT NULL');
        $this->addSql('ALTER TABLE card_template ADD req_rank_type VARCHAR(50) DEFAULT NULL, ADD req_rank_pos INT DEFAULT NULL');
        $this->addSql('ALTER TABLE title ADD req_rank_type VARCHAR(50) DEFAULT NULL, ADD req_rank_pos INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE avatar DROP req_rank_type, DROP req_rank_pos');
        $this->addSql('ALTER TABLE card_template DROP req_rank_type, DROP req_rank_pos');
        $this->addSql('ALTER TABLE title DROP req_rank_type, DROP req_rank_pos');
    }
}
