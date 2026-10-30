<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261019175548 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "student_profile.weekly_hours_available ENUM listesi genişletildi: '4 günden fazla' (sınırsız) kaldırıldı, yerine somut '4 gün'/'5 gün'/'6 gün'/'Her gün' eklendi (2026-10-19, kullanıcı kararı — GunlukKontrol kanalının 'sınırsız' bir seçenek yüzünden fiilen her gün tetiklenmesini önler).";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE student_profile MODIFY weekly_hours_available ENUM('1 gün','2 gün','3 gün','4 gün','5 gün','6 gün','Her gün') DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE student_profile MODIFY weekly_hours_available ENUM('1 gün','2 gün','3 gün','4 günden fazla') DEFAULT NULL");
    }
}
