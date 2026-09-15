<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260912144933 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Öğrenci aktivite modülü: ogrenci (demografik), login_log, '
            . 'materyal_erisim_log, sinav_sonucu tabloları (ogrenci_no ortak FK).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE login_log (id INT AUTO_INCREMENT NOT NULL, yil SMALLINT NOT NULL, donem SMALLINT NOT NULL, islem_zamani DATETIME NOT NULL, ogrenci_no INT NOT NULL, INDEX idx_login_log_ogrenci_no (ogrenci_no), INDEX idx_login_log_yil_donem (yil, donem), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE materyal_erisim_log (id INT AUTO_INCREMENT NOT NULL, ders_kodu VARCHAR(20) NOT NULL, yil SMALLINT NOT NULL, donem SMALLINT NOT NULL, materyal_tipi VARCHAR(50) NOT NULL, unite_no SMALLINT NOT NULL, islem_zamani DATETIME NOT NULL, ogrenci_no INT NOT NULL, INDEX idx_materyal_erisim_log_ogrenci_no (ogrenci_no), INDEX idx_materyal_erisim_log_ders_kodu (ders_kodu), INDEX idx_materyal_erisim_log_yil_donem (yil, donem), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ogrenci (ogrenci_no INT NOT NULL, cinsiyet VARCHAR(1) NOT NULL, dogum_tarihi DATE NOT NULL, PRIMARY KEY (ogrenci_no)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE sinav_sonucu (id INT AUTO_INCREMENT NOT NULL, ders_kodu VARCHAR(20) NOT NULL, yil SMALLINT NOT NULL, donem SMALLINT NOT NULL, puan SMALLINT NOT NULL, sure INT NOT NULL, uniteler JSON NOT NULL, bos SMALLINT NOT NULL, dogru SMALLINT NOT NULL, yanlis SMALLINT NOT NULL, soru_sayisi SMALLINT NOT NULL, islem_zamani DATETIME NOT NULL, ogrenci_no INT NOT NULL, INDEX idx_sinav_sonucu_ogrenci_no (ogrenci_no), INDEX idx_sinav_sonucu_ders_kodu (ders_kodu), INDEX idx_sinav_sonucu_yil_donem (yil, donem), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE login_log ADD CONSTRAINT FK_F16D9FFF274EA61A FOREIGN KEY (ogrenci_no) REFERENCES ogrenci (ogrenci_no) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE materyal_erisim_log ADD CONSTRAINT FK_73FC3E74274EA61A FOREIGN KEY (ogrenci_no) REFERENCES ogrenci (ogrenci_no) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE sinav_sonucu ADD CONSTRAINT FK_49A49514274EA61A FOREIGN KEY (ogrenci_no) REFERENCES ogrenci (ogrenci_no) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE login_log DROP FOREIGN KEY FK_F16D9FFF274EA61A');
        $this->addSql('ALTER TABLE materyal_erisim_log DROP FOREIGN KEY FK_73FC3E74274EA61A');
        $this->addSql('ALTER TABLE sinav_sonucu DROP FOREIGN KEY FK_49A49514274EA61A');
        $this->addSql('DROP TABLE login_log');
        $this->addSql('DROP TABLE materyal_erisim_log');
        $this->addSql('DROP TABLE ogrenci');
        $this->addSql('DROP TABLE sinav_sonucu');
    }
}
