<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261110140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'student_profile.learning_goal — YBS anketi Soru 8 artık DERS BEKLENTİSİ değil '
            .'KAYIT MOTİVASYONU soruyor (2026-11-10, kullanıcı kararı). Yeni seçenekler mastar '
            .'biçiminde ve içsel-dışsal motivasyon sürekliliği üzerinde tutarlı: Maaşımı '
            .'yükseltmek / Unvanımı değiştirmek / Kendimi gerçekleştirmek / Alanımla ilgili '
            .'bilgi edinmek. Son değer eski listeden AYNEN korundu (hem anlam karşılığı var hem '
            .'mevcut kayıtlar bozulmasın diye). Karşılığı olmayan üç eski değer '
            .'("İyi bir not almak", "Kariyerime katkı sağlamak", "Zorunlu olduğu için '
            .'alıyorum") NULL\'a çekiliyor: bunlar gerçek anket verisi değil, '
            .'app:import-ybs-anketi ile yüklenmiş SENTETİK test verisidir (7 satır) ve yeni '
            .'listeye eşlenmeleri veri uydurmak olurdu. Ayrıca bkz. '
            .'TriggerMessageSelector: "zorunlu" seçeneği kalktığı için T4 norm uygunluk '
            .'filtresinin learning_goal kapısı kaldırıldı.';
    }

    public function up(Schema $schema): void
    {
        // Önce kapsam dışı kalan değerleri boşalt — aksi halde ALTER veri kaybı hatası verir.
        $this->addSql(
            "UPDATE student_profile SET learning_goal = NULL "
            ."WHERE learning_goal IN ('İyi bir not almak', 'Kariyerime katkı sağlamak', "
            ."'Zorunlu olduğu için alıyorum')"
        );
        $this->addSql(
            "ALTER TABLE student_profile MODIFY learning_goal "
            ."ENUM('Maaşımı yükseltmek', 'Unvanımı değiştirmek', 'Kendimi gerçekleştirmek', "
            ."'Alanımla ilgili bilgi edinmek') DEFAULT NULL"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "UPDATE student_profile SET learning_goal = NULL "
            ."WHERE learning_goal IN ('Maaşımı yükseltmek', 'Unvanımı değiştirmek', "
            ."'Kendimi gerçekleştirmek')"
        );
        $this->addSql(
            "ALTER TABLE student_profile MODIFY learning_goal "
            ."ENUM('İyi bir not almak', 'Alanımla ilgili bilgi edinmek', "
            ."'Kariyerime katkı sağlamak', 'Zorunlu olduğu için alıyorum') DEFAULT NULL"
        );
    }
}
