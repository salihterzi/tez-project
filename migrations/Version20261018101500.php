<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * `student_profile.daily_reactive_message_*`'i `daily_message_*`'e yeniden adlandırır
 * (2026-10-18, kullanıcı kararı, aynı gün genişletildi) — sayaç artık sadece reaktif akış
 * yanıtlarını değil, öğrencinin gelen mesajlarını VE proaktif M1/T1-T4 gönderimini de
 * kapsıyor (bkz. {@see \App\Entity\StudentProfile::$dailyMessageCount}'un docblock'u).
 * `Version20261018095017`'de eklenen kolonların adı/anlamı genişledi; o migration zaten
 * çalıştırıldığı için kolonlar burada YENİDEN ADLANDIRILIYOR (DROP+ADD değil, veri korunur).
 */
final class Version20261018101500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'student_profile.daily_reactive_message_count/_sent_at -> daily_message_count/_at (sayacın kapsamı genişledi: gelen+giden+proaktif+reaktif).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE student_profile RENAME COLUMN daily_reactive_message_count TO daily_message_count');
        $this->addSql('ALTER TABLE student_profile RENAME COLUMN daily_reactive_message_sent_at TO daily_message_at');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE student_profile RENAME COLUMN daily_message_count TO daily_reactive_message_count');
        $this->addSql('ALTER TABLE student_profile RENAME COLUMN daily_message_at TO daily_reactive_message_sent_at');
    }
}
