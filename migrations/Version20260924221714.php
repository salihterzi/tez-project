<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260924221714 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'StudentProfile: M1 veri alanlarını ekler (time_barrier_choice, difficulty_barrier_choice, difficulty_note_text, motivation_barrier_choice); pending_flow_step yeni adımları ekler; message_frequency_preference 3 değere geri döner (Zamanlama_Kisisellestirme_Karar_Mantigi.docx doğrulaması).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE student_profile ADD time_barrier_choice ENUM(\'haftada_1_kisa_oturum\',\'tempo_ayni_kalsin\',\'ara_vermek_istiyorum\') DEFAULT NULL, ADD difficulty_barrier_choice ENUM(\'materyali_gozden_gecir\',\'foruma_bak\',\'notumu_birak\') DEFAULT NULL, ADD difficulty_note_text LONGTEXT DEFAULT NULL, ADD motivation_barrier_choice ENUM(\'hedef_hala_gecerli\',\'hedef_degisti\',\'emin_degil\') DEFAULT NULL, CHANGE message_frequency_preference message_frequency_preference ENUM(\'1\',\'2\',\'Yalnızca önemli olanlar\') DEFAULT NULL, CHANGE pending_flow_step pending_flow_step ENUM(\'ENGEL_BEKLENIYOR\',\'ZAMAN_TAKIBI_BEKLENIYOR\',\'ZAMAN_GUN_SAATI_BEKLENIYOR\',\'ZAMAN_DONUS_SURESI_BEKLENIYOR\',\'ZORLUK_TAKIBI_BEKLENIYOR\',\'ZORLUK_NOT_BEKLENIYOR\',\'MOTIVASYON_TAKIBI_BEKLENIYOR\',\'MOTIVASYON_YENI_HEDEF_BEKLENIYOR\') DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE student_profile DROP time_barrier_choice, DROP difficulty_barrier_choice, DROP difficulty_note_text, DROP motivation_barrier_choice, CHANGE message_frequency_preference message_frequency_preference ENUM(\'1\', \'2\', \'3\', \'4\', \'Yalnızca önemli olanlar\') DEFAULT NULL, CHANGE pending_flow_step pending_flow_step ENUM(\'ENGEL_BEKLENIYOR\', \'ZAMAN_TAKIBI_BEKLENIYOR\', \'ZAMAN_GUN_SAATI_BEKLENIYOR\', \'ZAMAN_DONUS_SURESI_BEKLENIYOR\', \'ZORLUK_TAKIBI_BEKLENIYOR\', \'MOTIVASYON_TAKIBI_BEKLENIYOR\') DEFAULT NULL');
    }
}
