<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260906153356 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'conversation_message tablosuna whatsapp_timestamp (öğrencinin gerçek yazma zamanı), '
            . 'delivered_at ve read_at (bizim gönderdiğimiz mesajların teslim/okundu durumu) eklenir.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE conversation_message ADD whatsapp_timestamp DATETIME DEFAULT NULL, ADD delivered_at DATETIME DEFAULT NULL, ADD read_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE conversation_message DROP whatsapp_timestamp, DROP delivered_at, DROP read_at');
    }
}
