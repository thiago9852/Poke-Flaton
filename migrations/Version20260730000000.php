<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated migration to add sub-maps and map portals.
 */
final class Version20260730000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds is_submap flag to map and creates map_portal table';
    }

    public function up(Schema $schema): void
    {
        // 1. Add is_submap to map table
        $this->addSql('ALTER TABLE map ADD is_submap TINYINT(1) DEFAULT 0 NOT NULL');

        // 2. Create map_portal table
        $this->addSql('CREATE TABLE map_portal (id INT AUTO_INCREMENT NOT NULL, parent_map_id INT NOT NULL, target_map_id INT NOT NULL, latitude DOUBLE PRECISION NOT NULL, longitude DOUBLE PRECISION NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_A52A4A836DDF4B7F (parent_map_id), INDEX IDX_A52A4A83158309A3 (target_map_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // 3. Add foreign keys for map_portal
        $this->addSql('ALTER TABLE map_portal ADD CONSTRAINT FK_A52A4A836DDF4B7F FOREIGN KEY (parent_map_id) REFERENCES map (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE map_portal ADD CONSTRAINT FK_A52A4A83158309A3 FOREIGN KEY (target_map_id) REFERENCES map (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // 1. Drop foreign keys and map_portal table
        $this->addSql('ALTER TABLE map_portal DROP FOREIGN KEY FK_A52A4A836DDF4B7F');
        $this->addSql('ALTER TABLE map_portal DROP FOREIGN KEY FK_A52A4A83158309A3');
        $this->addSql('DROP TABLE map_portal');

        // 2. Drop is_submap column from map table
        $this->addSql('ALTER TABLE map DROP is_submap');
    }
}
