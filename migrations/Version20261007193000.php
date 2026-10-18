<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261007193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'scheduled_message: kind enum\'una sinav_gunu eklenir, trigger+persona nullable olur — Sınav Günü "iyi şanslar" mesajının gerçek gönderime bağlanması için (T1-T4\'ten bağımsız, kişiselleştirilmemiş).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE scheduled_message CHANGE kind kind ENUM('m1','variant','reminder','sinav_gunu') NOT NULL");
        $this->addSql("ALTER TABLE scheduled_message CHANGE `trigger` `trigger` ENUM('T1','T2','T3','T4') DEFAULT NULL");
        $this->addSql('ALTER TABLE scheduled_message CHANGE persona persona VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE scheduled_message CHANGE persona persona VARCHAR(20) NOT NULL');
        $this->addSql("ALTER TABLE scheduled_message CHANGE `trigger` `trigger` ENUM('T1','T2','T3','T4') NOT NULL");
        $this->addSql("ALTER TABLE scheduled_message CHANGE kind kind ENUM('m1','variant','reminder') NOT NULL");
    }
}
