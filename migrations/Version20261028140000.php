<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261028140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'student_profile.gunluk_sinir_bildirildi_at (2026-10-28, kullanıcı kararı): '
            .'"bugünlük mesajlaşma sınırına ulaşıldı" bildirimi gün içinde BİR KERE gönderilir. '
            .'Önceden sınır aşıldıktan sonra gelen HER mesajda tekrar gönderiliyordu — yani '
            .'mesajlaşmayı kesmesi gereken mekanizma sınırsız mesaj üretiyordu. Boolean yerine '
            .'tarih alanı: daily_message_at ile aynı mantıkla gün karşılaştırmasıyla kendiliğinden '
            .'sıfırlanır, ayrı bir gece sıfırlama işi gerekmez.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE student_profile ADD gunluk_sinir_bildirildi_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE student_profile DROP gunluk_sinir_bildirildi_at');
    }
}
