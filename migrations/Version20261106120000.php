<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261106120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ogrenci_erisim_ozeti.onceki_hafta_erisim_sayisi — T3 artık İKİ ardışık '
            .'yarılanma istiyor (2026-11-06, kullanıcı kararı), bu yüzden üçüncü bir haftalık '
            .'pencere gerekiyor: [bugün-21, bugün-14). Ölçüm gerekçesi: tek haftalık '
            .'karşılaştırma (bu < geçen × 0,5) 2024-2025 bahar verisinde öğrenci-günlerin '
            .'%55,4\'ünde, öğrencilerin %95,1\'inde tetikleniyordu — yani yarılanma bu '
            .'popülasyonda anomali değil, olağan haftalık dalgalanma. İki ardışık yarılanma '
            .'şartı tetiklenmeyi %12,1 gün / %41,7 öğrenciye indirirken ileri yönlü isabeti '
            .'de artırıyor (kaldıraç 1,37× → 1,71×).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE ogrenci_erisim_ozeti '
            .'ADD onceki_hafta_erisim_sayisi INT DEFAULT 0 NOT NULL'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ogrenci_erisim_ozeti DROP onceki_hafta_erisim_sayisi');
    }
}
