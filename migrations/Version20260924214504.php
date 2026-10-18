<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260924214504 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'sent_message_log (append-only) tablosunu ekler — tetikleyici (T1-T4) varyant rotasyonu için gönderim geçmişi.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE sent_message_log (id INT AUTO_INCREMENT NOT NULL, student_id INT NOT NULL, `trigger` ENUM(\'T1\',\'T2\',\'T3\',\'T4\') NOT NULL, variant_no SMALLINT NOT NULL, sent_at DATETIME NOT NULL, INDEX idx_sent_message_log_student_trigger (student_id, `trigger`), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE sent_message_log');
    }
}
