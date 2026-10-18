<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260924224829 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'sent_message_log: 72 saat yanıtsızlık mantığı için replied_at/reminder_sent_at ekler.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sent_message_log ADD replied_at DATETIME DEFAULT NULL, ADD reminder_sent_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sent_message_log DROP replied_at, DROP reminder_sent_at');
    }
}
