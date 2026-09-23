<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260923221605 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE student_profile CHANGE preferred_study_time preferred_study_time ENUM(\'Sabah\',\'Öğleden sonra\',\'Akşam\',\'Gece\',\'Değişken\') DEFAULT NULL, CHANGE weekly_hours_available weekly_hours_available ENUM(\'1-2\',\'3-5\',\'6+\') DEFAULT NULL, CHANGE message_frequency_preference message_frequency_preference ENUM(\'1\',\'2\',\'Yalnızca önemli olanlar\') DEFAULT NULL, CHANGE social_comparison_optin social_comparison_optin ENUM(\'Evet\',\'Hayır\',\'Fark etmez\') DEFAULT NULL, CHANGE learning_goal learning_goal ENUM(\'İyi bir not almak\',\'Alanımla ilgili bilgi edinmek\',\'Kariyerime katkı sağlamak\',\'Zorunlu olduğu için alıyorum\') DEFAULT NULL, CHANGE current_state current_state ENUM(\'YENİ\',\'AKTİF\',\'YAVAŞLAYAN\',\'PASİF\',\'KOHORT_GERİSİNDE\',\'ARA_VERMİŞ\') NOT NULL DEFAULT \'YENİ\', CHANGE last_barrier_type last_barrier_type ENUM(\'zaman\',\'zorluk\',\'motivasyon\') DEFAULT NULL, CHANGE observed_time_window observed_time_window ENUM(\'06:00-12:00\',\'12:00-18:00\',\'18:00-24:00\',\'00:00-06:00\') DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE student_profile CHANGE preferred_study_time preferred_study_time VARCHAR(20) DEFAULT NULL, CHANGE weekly_hours_available weekly_hours_available VARCHAR(20) DEFAULT NULL, CHANGE message_frequency_preference message_frequency_preference VARCHAR(30) DEFAULT NULL, CHANGE social_comparison_optin social_comparison_optin VARCHAR(10) DEFAULT NULL, CHANGE learning_goal learning_goal VARCHAR(50) DEFAULT NULL, CHANGE current_state current_state VARCHAR(20) DEFAULT NULL, CHANGE last_barrier_type last_barrier_type VARCHAR(20) DEFAULT NULL, CHANGE observed_time_window observed_time_window VARCHAR(20) DEFAULT NULL');
    }
}
