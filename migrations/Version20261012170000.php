<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261012170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ogrenci.calisma_saati_baslangic/bitis kolonları kaldırıldı (kullanıcı kararı 2026-10-12) — YBS anketi bu veriyi hiç toplamıyor (gerçek pilotta hep boş kalacaktı), yalnızca sentetik test verisinde rastgele dolduruluyordu. Zamanlama artık yalnızca öğrencinin tercih ettiği zaman aralığına (preferred_study_time, "Değişken" ise faz bazlı gözlenen istatistiğe) dayanıyor.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ogrenci DROP COLUMN calisma_saati_baslangic');
        $this->addSql('ALTER TABLE ogrenci DROP COLUMN calisma_saati_bitis');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ogrenci ADD COLUMN calisma_saati_baslangic TIME DEFAULT NULL');
        $this->addSql('ALTER TABLE ogrenci ADD COLUMN calisma_saati_bitis TIME DEFAULT NULL');
    }
}
