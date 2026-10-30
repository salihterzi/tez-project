<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261029090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'student_profile.oturum_kaynagi kaldırıldı + gunluk_kontrol_tiklama tablosu eklendi '
            .'(2026-10-29, kullanıcı kararı). Günlük kontrol mesajının butonu QUICK_REPLY yerine URL '
            .'oluyor: öğrenci tek dokunuşla e-kampüs\'e gidiyor. URL butonu webhook\'a sinyal '
            .'GÖNDERMEDİĞİ için oturum kaynağına göre "seçeneklerden birini seçin" uyarısı gönderme '
            .'ihtiyacı ortadan kalktı (bir gün önce eklenmişti, kullanılmadan kaldırıldı). Tıklama '
            .'ölçümü kaybolmasın diye link kendi alan adımızdan geçirilip (yönlendirme uç noktası) '
            .'bu tabloya kaydedilecek.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE student_profile DROP oturum_kaynagi');
        $this->addSql(<<<'SQL'
            CREATE TABLE gunluk_kontrol_tiklama (
                id INT AUTO_INCREMENT NOT NULL,
                student_id INT NOT NULL,
                hedef VARCHAR(32) NOT NULL,
                tiklandi_at DATETIME NOT NULL,
                user_agent VARCHAR(255) DEFAULT NULL,
                INDEX idx_gunluk_kontrol_tiklama_student (student_id),
                INDEX idx_gunluk_kontrol_tiklama_at (tiklandi_at),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE gunluk_kontrol_tiklama');
        $this->addSql('ALTER TABLE student_profile ADD oturum_kaynagi VARCHAR(32) DEFAULT NULL');
    }
}
