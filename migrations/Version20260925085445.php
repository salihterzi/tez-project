<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260925085445 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ogrenci: ad/soyad/telefon_numarasi ekler (mesaj kişiselleştirme + gerçek WhatsApp gönderimi için). StudentProfile: pending_flow_step ve difficulty_barrier_choice ENUM listelerinden kaldırılan seçenekleri (motivasyon yeni-hedef sorusu, forum) düşer.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ogrenci ADD ad VARCHAR(100) DEFAULT NULL, ADD soyad VARCHAR(100) DEFAULT NULL, ADD telefon_numarasi VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE student_profile CHANGE pending_flow_step pending_flow_step ENUM(\'ENGEL_BEKLENIYOR\',\'ZAMAN_TAKIBI_BEKLENIYOR\',\'ZAMAN_GUN_SAATI_BEKLENIYOR\',\'ZAMAN_DONUS_SURESI_BEKLENIYOR\',\'ZORLUK_TAKIBI_BEKLENIYOR\',\'ZORLUK_NOT_BEKLENIYOR\',\'MOTIVASYON_TAKIBI_BEKLENIYOR\') DEFAULT NULL, CHANGE difficulty_barrier_choice difficulty_barrier_choice ENUM(\'materyali_gozden_gecir\',\'notumu_birak\') DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ogrenci DROP ad, DROP soyad, DROP telefon_numarasi');
        $this->addSql('ALTER TABLE student_profile CHANGE pending_flow_step pending_flow_step ENUM(\'ENGEL_BEKLENIYOR\', \'ZAMAN_TAKIBI_BEKLENIYOR\', \'ZAMAN_GUN_SAATI_BEKLENIYOR\', \'ZAMAN_DONUS_SURESI_BEKLENIYOR\', \'ZORLUK_TAKIBI_BEKLENIYOR\', \'ZORLUK_NOT_BEKLENIYOR\', \'MOTIVASYON_TAKIBI_BEKLENIYOR\', \'MOTIVASYON_YENI_HEDEF_BEKLENIYOR\') DEFAULT NULL, CHANGE difficulty_barrier_choice difficulty_barrier_choice ENUM(\'materyali_gozden_gecir\', \'foruma_bak\', \'notumu_birak\') DEFAULT NULL');
    }
}
