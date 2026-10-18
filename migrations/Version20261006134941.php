<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261006134941 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "scheduled_message.kind: 'reminder' eklenir — 72 saat yanıtsızlık hatırlatması artık gerçekten zamanlanıp gönderiliyor (ScheduledMessageKind::Reminder).";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE scheduled_message CHANGE kind kind ENUM('m1','variant','reminder') NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE scheduled_message CHANGE kind kind ENUM('m1','variant') NOT NULL");
    }
}
