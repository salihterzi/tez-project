<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260924191145 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'weekly_interaction_log (append-only) ve term_phases referans tablolarını ekler';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE term_phases (id INT AUTO_INCREMENT NOT NULL, term_id INT NOT NULL, phase ENUM(\'Normal\',\'Sinav_Oncesi\',\'Sinav_Gunu\') NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, INDEX idx_term_phases_term_id (term_id), UNIQUE INDEX uniq_term_phases_term_id_phase (term_id, phase), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE weekly_interaction_log (id INT AUTO_INCREMENT NOT NULL, student_id INT NOT NULL, material_id INT NOT NULL, accessed_at DATETIME NOT NULL, INDEX idx_weekly_interaction_log_student_id (student_id), INDEX idx_weekly_interaction_log_material_id (material_id), INDEX idx_weekly_interaction_log_accessed_at (accessed_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE term_phases');
        $this->addSql('DROP TABLE weekly_interaction_log');
    }
}
