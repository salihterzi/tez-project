<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261012140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'student_profile.preferred_study_time: "Gece" kaldırılıp "Öğle" eklendi (kullanıcı kararı 2026-10-12) — ZAMAN_SAAT_SORUSU artık Sabah/Öğle/Akşam sunuyor (temsilci saatler: 09:00/13:00/19:00). Mevcut veride "Gece" değerini kullanan satır yoktu (kontrol edildi, tek dolu satır "Akşam").';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE student_profile CHANGE preferred_study_time preferred_study_time ENUM('Sabah','Öğle','Akşam','Değişken') DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE student_profile CHANGE preferred_study_time preferred_study_time ENUM('Sabah','Akşam','Gece','Değişken') DEFAULT NULL");
    }
}
