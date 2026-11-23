<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261111100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'student_profile.learning_goal → enrollment_motivation (2026-11-11, kullanıcı '
            .'kararı). Alan adı anlamından sapmıştı: YBS anketi Soru 8 bir önceki migration\'da '
            .'(Version20261110140000) "ders beklentisi"nden "KAYIT MOTİVASYONU"na çevrilmişti, '
            .'ama kolon adı hâlâ öğrenme hedefi diyordu. Tez yöntem bölümü ve ekleri bu alan '
            .'adlarına atıf verecek; "learning_goal" adıyla kayıt motivasyonu ölçtüğünü yazmak '
            .'tutarsızlık olurdu. Yeni ad, anket alanı grubunun geri kalanıyla (participation_'
            .'consent, student_term, weekly_hours_available, preferred_study_time, social_'
            .'comparison_optin) dil olarak tutarlıdır. Enum değerleri ve kolon tipi DEĞİŞMEDİ — '
            .'yalnızca ad. Kod tarafında App\\Enum\\LearningGoal → App\\Enum\\EnrollmentMotivation '
            .'(sınıf, dosya, 47 atıf, getter/setter ve repository ham SQL\'i dahil).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE student_profile CHANGE learning_goal enrollment_motivation "
            ."ENUM('Maaşımı yükseltmek', 'Unvanımı değiştirmek', 'Kendimi gerçekleştirmek', "
            ."'Alanımla ilgili bilgi edinmek') DEFAULT NULL"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE student_profile CHANGE enrollment_motivation learning_goal "
            ."ENUM('Maaşımı yükseltmek', 'Unvanımı değiştirmek', 'Kendimi gerçekleştirmek', "
            ."'Alanımla ilgili bilgi edinmek') DEFAULT NULL"
        );
    }
}
