<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260924213906 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'term_phases: (term_id, phase) unique kısıtını kaldırır (faz bir dönemde birden fazla kez tekrarlanabilir) ve gerçek Bahar 2027 dönem takvimini (term_id=1) tohumlar.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_term_phases_term_id_phase ON term_phases');

        // Donem_Mesaj_Takvimi_ve_Sablonlari.docx — Bahar Dönemi 2027 takvimi.
        $this->addSql("INSERT INTO term_phases (term_id, phase, start_date, end_date) VALUES
            (1, 'Normal', '2027-02-23', '2027-03-26'),
            (1, 'Sinav_Oncesi', '2027-03-27', '2027-04-09'),
            (1, 'Sinav_Gunu', '2027-04-10', '2027-04-11'),
            (1, 'Normal', '2027-04-12', '2027-05-14'),
            (1, 'Sinav_Oncesi', '2027-05-15', '2027-05-28'),
            (1, 'Sinav_Gunu', '2027-05-29', '2027-05-30')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM term_phases WHERE term_id = 1 AND start_date >= '2027-02-23' AND end_date <= '2027-05-30'");
        $this->addSql('CREATE UNIQUE INDEX uniq_term_phases_term_id_phase ON term_phases (term_id, phase)');
    }
}
