<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261029100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'gunluk_kontrol_tiklama tablosu kaldırıldı (2026-10-29, kullanıcı kararı): tıklama '
            .'takibi şimdilik yapılmayacak, başka bir yolla ölçülecek. Günlük kontrol mesajının URL '
            .'butonu doğrudan ekampus.anadolu.edu.tr\'ye gidiyor — araya kendi yönlendirme uç '
            .'noktamız girmiyor. Karar değişirse YonlendirmeController ve bu tablo geri getirilebilir '
            .'(bkz. Version20261029090000).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE gunluk_kontrol_tiklama');
    }

    public function down(Schema $schema): void
    {
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
}
