<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260902130310 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Faz 1: conversation_session + conversation_message tabloları (çok turlu WhatsApp konuşma oturumları). messenger_messages tablosu doctrine transport için standart olarak eklenir.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE conversation_message (id INT AUTO_INCREMENT NOT NULL, role VARCHAR(16) NOT NULL, content LONGTEXT NOT NULL, whatsapp_message_id VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, session_id INT NOT NULL, INDEX IDX_2DEB3E75613FECDF (session_id), UNIQUE INDEX uniq_message_whatsapp_id (whatsapp_message_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE conversation_session (id INT AUTO_INCREMENT NOT NULL, phone_number VARCHAR(32) NOT NULL, status VARCHAR(16) NOT NULL, turn_count INT NOT NULL, max_turns INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX idx_session_phone_status (phone_number, status), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE conversation_message ADD CONSTRAINT FK_2DEB3E75613FECDF FOREIGN KEY (session_id) REFERENCES conversation_session (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE conversation_message DROP FOREIGN KEY FK_2DEB3E75613FECDF');
        $this->addSql('DROP TABLE conversation_message');
        $this->addSql('DROP TABLE conversation_session');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
