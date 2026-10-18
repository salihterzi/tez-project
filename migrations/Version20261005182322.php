<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * `student_profile.weekly_hours_available` ve `.message_frequency_preference`'ı YBS kayıt
 * anketinin (`YBS_Arastirma_Kayit_Formu (2).docx`) GERÇEK Soru 3/Soru 4 seçenekleriyle
 * eşitler — kullanıcı kararı (2026-10-05):
 * - Soru 3 ("Haftada eğitime ortalama kaç gün ayırabilirsiniz?"): önceki tur bu alanı saat
 *   aralığı (`1-2`/`3-5`/`6+`) olarak tasarlamıştı ama hiç toplanmamıştı (veri yoktu) — artık
 *   formun gerçek gün-sayısı seçenekleriyle değiştirildi.
 * - Soru 4 ("Haftada kaç mesaj almak size uygun olur?"): eksik olan 4. seçenek ("Çalışacağım
 *   günlerin her biri") eklendi.
 * Bu alanların nasıl kullanılacağı (MessageFrequencyPolicy/tempo önerisi) henüz
 * kararlaştırılmadı — bkz. `roadmap-faz.md` Faz B.
 */
final class Version20261005182322 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'student_profile.weekly_hours_available/.message_frequency_preference: YBS anketinin gerçek Soru 3/4 seçenekleriyle eşitler.';
    }

    public function up(Schema $schema): void
    {
        // Eski (hiç toplanmamış, test amaçlı elle yazılmış) değerler yeni enum'da anlamsız —
        // yeni enum'a geçmeden önce temizlenir (tek satır: öğrenci #1, manuel test verisi).
        $this->addSql("UPDATE student_profile SET weekly_hours_available = NULL WHERE weekly_hours_available NOT IN ('1 gün','2 gün','3 gün','4 günden fazla')");
        $this->addSql("ALTER TABLE student_profile CHANGE weekly_hours_available weekly_hours_available ENUM('1 gün','2 gün','3 gün','4 günden fazla') DEFAULT NULL");
        $this->addSql("ALTER TABLE student_profile CHANGE message_frequency_preference message_frequency_preference ENUM('1','2','Çalışacağım günlerin her biri','Yalnızca önemli olanlar') DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE student_profile CHANGE weekly_hours_available weekly_hours_available ENUM('1-2','3-5','6+') DEFAULT NULL");
        $this->addSql("ALTER TABLE student_profile CHANGE message_frequency_preference message_frequency_preference ENUM('1','2','Yalnızca önemli olanlar') DEFAULT NULL");
    }
}
