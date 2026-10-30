<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261028150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'student_profile.secenek_hatirlatmasi_gonderildi (2026-10-28, kullanıcı kararı): '
            .'bekleyen bir akış adımı varken öğrenci tanınmayan serbest metin yazarsa, OTURUMDA '
            .'BİR KEZ "lütfen seçeneklerden birini seçin" hatırlatması gönderilir; öğrenci devam '
            .'ederse sessiz kalınır. Önceden bu durum tamamen sessizdi: öğrenci aktif bir soruya '
            .'cevap vermeye çalışıyor, beceremiyor, hiçbir tepki alamıyor ve her denemesi günlük '
            .'sayacı tüketiyordu. Bayrak StudentProfile::oturumuBaslat() içinde sıfırlanır.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE student_profile ADD secenek_hatirlatmasi_gonderildi TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE student_profile DROP secenek_hatirlatmasi_gonderildi');
    }
}
