<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * scheduled_message tablosunu ekler — gece 02:30'daki karar (kime ne gönderilecek) ile
 * GERÇEK gönderimi (öğrencinin observed_days/tercih saatine göre) ayrıştırmak için
 * (bkz. App\Entity\ScheduledMessage, App\Command\DispatchScheduledMessagesCommand).
 */
final class Version20261005130501 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'scheduled_message (kuyruk) tablosunu ekler — zamanlanmış gerçek WhatsApp gönderimi için.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE scheduled_message (
                id INT AUTO_INCREMENT NOT NULL,
                student_id INT NOT NULL,
                kind ENUM('m1','variant') NOT NULL,
                `trigger` ENUM('T1','T2','T3','T4') NOT NULL,
                variant_no SMALLINT DEFAULT NULL,
                template_meta_name VARCHAR(255) NOT NULL,
                body_parameters JSON NOT NULL,
                message_text LONGTEXT NOT NULL,
                telefon VARCHAR(20) NOT NULL,
                persona VARCHAR(20) NOT NULL,
                target_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL,
                sent_at DATETIME DEFAULT NULL,
                wamid VARCHAR(255) DEFAULT NULL,
                last_attempt_at DATETIME DEFAULT NULL,
                last_error LONGTEXT DEFAULT NULL,
                INDEX idx_scheduled_message_due (sent_at, target_at),
                INDEX idx_scheduled_message_student (student_id),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE scheduled_message');
    }
}
