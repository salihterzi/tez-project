<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260925094922 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'weekly_interaction_log kaldırılır (hiç dolmadı, kaynak veri zaten materyal_erisim_log). Yerine ogrenci_erisim_ozeti eklenir — Python veri pipeline\'ının materyal_erisim_log/sinav_sonucu\'ndan türeteceği hafif özet tablosu (bkz. OgrenciErisimOzeti entity docblock\'u).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ogrenci_erisim_ozeti (student_id INT NOT NULL, ilk_erisim_tarihi DATETIME DEFAULT NULL, son_erisim_tarihi DATETIME DEFAULT NULL, bu_hafta_erisim_sayisi INT NOT NULL, gecen_hafta_erisim_sayisi INT NOT NULL, donem_kumulatif_erisim_sayisi INT NOT NULL, guncellenme_tarihi DATETIME NOT NULL, PRIMARY KEY (student_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('DROP TABLE weekly_interaction_log');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE weekly_interaction_log (id INT AUTO_INCREMENT NOT NULL, student_id INT NOT NULL, material_id INT NOT NULL, accessed_at DATETIME NOT NULL, INDEX idx_weekly_interaction_log_accessed_at (accessed_at), INDEX idx_weekly_interaction_log_material_id (material_id), INDEX idx_weekly_interaction_log_student_id (student_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('DROP TABLE ogrenci_erisim_ozeti');
    }
}
