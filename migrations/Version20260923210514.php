<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260923210514 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE student_profile (student_term SMALLINT DEFAULT NULL, preferred_study_time VARCHAR(20) DEFAULT NULL, weekly_hours_available VARCHAR(20) DEFAULT NULL, message_frequency_preference VARCHAR(30) DEFAULT NULL, social_comparison_optin VARCHAR(10) DEFAULT NULL, learning_goal VARCHAR(50) DEFAULT NULL, current_state VARCHAR(20) DEFAULT NULL, last_barrier_type VARCHAR(20) DEFAULT NULL, observed_time_window VARCHAR(20) DEFAULT NULL, silence_until DATE DEFAULT NULL, last_message_sent_at DATETIME DEFAULT NULL, messages_sent_this_week SMALLINT NOT NULL, updated_at DATETIME NOT NULL, student_id INT NOT NULL, PRIMARY KEY (student_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE student_profile ADD CONSTRAINT FK_6C611FF7CB944F1A FOREIGN KEY (student_id) REFERENCES ogrenci (ogrenci_no)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE student_profile DROP FOREIGN KEY FK_6C611FF7CB944F1A');
        $this->addSql('DROP TABLE student_profile');
    }
}
