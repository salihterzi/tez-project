<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261004093000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'student_profile.motivation_barrier_choice: "hedef_degisti" (Değişti) seçeneği kaldırılır — templates.docx\'in son halinde bu dal tamamen kaldırıldı (MOTIVASYON_TAKIP artık yalnızca 2 seçenekli: Evet geçerli / Emin değilim). Mevcut veride bu değeri kullanan satır yoktu (kontrol edildi).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE student_profile CHANGE motivation_barrier_choice motivation_barrier_choice ENUM('hedef_hala_gecerli','emin_degil') DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE student_profile CHANGE motivation_barrier_choice motivation_barrier_choice ENUM('hedef_hala_gecerli','hedef_degisti','emin_degil') DEFAULT NULL");
    }
}
