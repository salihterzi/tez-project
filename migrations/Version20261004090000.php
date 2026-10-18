<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261004090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'student_profile.preferred_study_time: "Öğleden sonra" kaldırılır — templates.docx\'in son halinde WhatsApp\'ın şablon başına 3 buton sınırı nedeniyle bu seçenek düşürüldü (ZAMAN_SAAT_SORUSU artık Sabah/Akşam/Gece). Mevcut veride bu değeri kullanan satır yoktu (kontrol edildi).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE student_profile CHANGE preferred_study_time preferred_study_time ENUM('Sabah','Akşam','Gece','Değişken') DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE student_profile CHANGE preferred_study_time preferred_study_time ENUM('Sabah','Öğleden sonra','Akşam','Gece','Değişken') DEFAULT NULL");
    }
}
