<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260924223316 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'StudentProfile.observed_time_window\'ı faza göre ikiye böler (observed_time_window_normal, observed_time_window_sinav_oncesi) — Zamanlama_Kisisellestirme_Karar_Mantigi.docx Bölüm 9.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE student_profile ADD observed_time_window_normal ENUM(\'06:00-12:00\',\'12:00-18:00\',\'18:00-24:00\',\'00:00-06:00\') DEFAULT NULL, ADD observed_time_window_sinav_oncesi ENUM(\'06:00-12:00\',\'12:00-18:00\',\'18:00-24:00\',\'00:00-06:00\') DEFAULT NULL, DROP observed_time_window');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE student_profile ADD observed_time_window ENUM(\'06:00-12:00\', \'12:00-18:00\', \'18:00-24:00\', \'00:00-06:00\') DEFAULT NULL, DROP observed_time_window_normal, DROP observed_time_window_sinav_oncesi');
    }
}
