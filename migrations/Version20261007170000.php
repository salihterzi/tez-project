<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261007170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'student_profile.pending_flow_step: TETIKLEYICI_SECENEK_YANITI_BEKLENIYOR eklenir — T2/T3 Sınav Öncesi ve T4 kendi seçenek mesajlarının (templates.docx Bölüm 3, K1/K2/K3 kapanışları) akışa bağlanması için.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE student_profile CHANGE pending_flow_step pending_flow_step ENUM('ENGEL_BEKLENIYOR','ZAMAN_TAKIBI_BEKLENIYOR','ZAMAN_GUN_SAATI_BEKLENIYOR','ZAMAN_DONUS_SURESI_BEKLENIYOR','ZORLUK_TAKIBI_BEKLENIYOR','ZORLUK_NOT_BEKLENIYOR','MOTIVASYON_TAKIBI_BEKLENIYOR','T1_YANITI_BEKLENIYOR','TETIKLEYICI_SECENEK_YANITI_BEKLENIYOR') DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE student_profile CHANGE pending_flow_step pending_flow_step ENUM('ENGEL_BEKLENIYOR','ZAMAN_TAKIBI_BEKLENIYOR','ZAMAN_GUN_SAATI_BEKLENIYOR','ZAMAN_DONUS_SURESI_BEKLENIYOR','ZORLUK_TAKIBI_BEKLENIYOR','ZORLUK_NOT_BEKLENIYOR','MOTIVASYON_TAKIBI_BEKLENIYOR','T1_YANITI_BEKLENIYOR') DEFAULT NULL");
    }
}
