<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * `student_profile`'a günlük reaktif mesaj sayacını ekler (2026-10-18, kullanıcı kararı:
 * "her gün karşılıklı gönderilen mesaj sayısını 10 olarak sınırlamak" — bkz.
 * {@see \App\Entity\StudentProfile::$dailyReactiveMessageCount}'un docblock'u).
 *
 * NOT: `doctrine:migrations:diff` burada KASITLI olarak kullanılmadı — otomatik üretilen
 * diff, bu projenin önceden var olan (bu değişiklikle ilgisiz) şema sapmalarını da
 * yakalıyordu; en vahimi `gun_tahmini_gecmisi`/`saat_tahmini_gecmisi` tablolarını DROP
 * ediyordu (Python'un kendi yönettiği, Doctrine şemasının parçası OLMAYAN tablolar —
 * bkz. o tabloları oluşturan Python scriptlerinin docblock'u). Bu migration SADECE
 * istenen iki kolonu ekler.
 */
final class Version20261018095017 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'student_profile.daily_reactive_message_count/_sent_at eklenir — öğrencinin kendi diyaloğuna verilen reaktif yanıtların günlük sınırı (10) için.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE student_profile ADD daily_reactive_message_count SMALLINT NOT NULL DEFAULT 0, ADD daily_reactive_message_sent_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE student_profile DROP daily_reactive_message_count, DROP daily_reactive_message_sent_at');
    }
}
