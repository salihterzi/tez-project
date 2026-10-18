<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261009120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'student_profile.pending_flow_step: HATIRLATMA_72_SAAT_YANITI_BEKLENIYOR eklenir — 72 saat yanıtsızlık hatırlatması artık butonlu/aksiyon alınabilir olduğu için (Evet/1 hafta sonra/Mesaj göndermeyin) gerçek bir bekleyen adımı var.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE student_profile CHANGE pending_flow_step pending_flow_step ENUM('ENGEL_BEKLENIYOR','ZAMAN_TAKIBI_BEKLENIYOR','ZAMAN_GUN_SAATI_BEKLENIYOR','ZAMAN_DONUS_SURESI_BEKLENIYOR','ZORLUK_TAKIBI_BEKLENIYOR','ZORLUK_NOT_BEKLENIYOR','MOTIVASYON_TAKIBI_BEKLENIYOR','T1_YANITI_BEKLENIYOR','TETIKLEYICI_SECENEK_YANITI_BEKLENIYOR','HATIRLATMA_72_SAAT_YANITI_BEKLENIYOR') DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE student_profile CHANGE pending_flow_step pending_flow_step ENUM('ENGEL_BEKLENIYOR','ZAMAN_TAKIBI_BEKLENIYOR','ZAMAN_GUN_SAATI_BEKLENIYOR','ZAMAN_DONUS_SURESI_BEKLENIYOR','ZORLUK_TAKIBI_BEKLENIYOR','ZORLUK_NOT_BEKLENIYOR','MOTIVASYON_TAKIBI_BEKLENIYOR','T1_YANITI_BEKLENIYOR','TETIKLEYICI_SECENEK_YANITI_BEKLENIYOR') DEFAULT NULL");
    }
}
