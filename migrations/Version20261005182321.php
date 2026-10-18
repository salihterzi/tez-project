<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * `ogrenci.aile_sorumlulugu` kolonunu kaldırır — kullanıcı kararı (2026-10-05): hiçbir PHP
 * kodu bu alanı okumuyordu (yalnızca Python pipeline'ı SELECT'e dahil ediyordu, ama kendi
 * yorumunda "bilinçli olarak aday değişken DEĞİL" diyerek modelde hiç kullanmıyordu — bkz.
 * `python/mesaj_zamanlamasi_pipeline-v2.py`, o script de bu turda güncellendi).
 */
final class Version20261005182321 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ogrenci.aile_sorumlulugu kolonunu kaldırır — hiçbir kod tarafından kullanılmıyordu.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ogrenci DROP aile_sorumlulugu');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ogrenci ADD aile_sorumlulugu TINYINT(1) DEFAULT NULL');
    }
}
