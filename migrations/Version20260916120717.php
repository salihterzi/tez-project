<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260916120717 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE login_log DROP FOREIGN KEY `FK_F16D9FFF274EA61A`');
        $this->addSql('ALTER TABLE materyal_erisim_log DROP FOREIGN KEY `FK_73FC3E74274EA61A`');
        $this->addSql('ALTER TABLE sinav_sonucu DROP FOREIGN KEY `FK_49A49514274EA61A`');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE login_log ADD CONSTRAINT `FK_F16D9FFF274EA61A` FOREIGN KEY (ogrenci_no) REFERENCES ogrenci (ogrenci_no) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE materyal_erisim_log ADD CONSTRAINT `FK_73FC3E74274EA61A` FOREIGN KEY (ogrenci_no) REFERENCES ogrenci (ogrenci_no) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE sinav_sonucu ADD CONSTRAINT `FK_49A49514274EA61A` FOREIGN KEY (ogrenci_no) REFERENCES ogrenci (ogrenci_no) ON UPDATE NO ACTION ON DELETE CASCADE');
    }
}
