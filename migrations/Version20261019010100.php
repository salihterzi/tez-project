<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261019010100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'scheduled_message.kind ENUM listesine gunluk_kontrol eklenir — 2026-10-19 kullanıcı kararı: T1-T4\'ten ayrı, observed_days günlerinde trigger-kaynaklı mesaj gitmediğinde devreye giren hatırlatma/övgü kanalı (bkz. ScheduledMessageKind::GunlukKontrol docblock\'u).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE scheduled_message MODIFY kind ENUM('m1','variant','reminder','sinav_gunu','gunluk_kontrol') NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE scheduled_message MODIFY kind ENUM('m1','variant','reminder','sinav_gunu') NOT NULL");
    }
}
