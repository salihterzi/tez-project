<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261028130500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'student_profile.akis_tamamlandi_bildirildi (2026-10-28, kullanıcı kararı): '
            .'"akış tamamlandı" bilgilendirmesi her akış kapanışında BİR KERE gönderilir. '
            .'İlk sürümde öğrenci her yazdığında tekrar gidiyordu. Bayrak, boş olmayan bir '
            .'pending_flow_step atandığında (yeni akış başladığında) StudentProfile::setPendingFlowStep() '
            .'içinde otomatik false\'a döner.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE student_profile ADD akis_tamamlandi_bildirildi TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE student_profile DROP akis_tamamlandi_bildirildi');
    }
}
