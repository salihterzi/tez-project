<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260925110931 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase (Sinav_Oncesi -> Ara_Sinav_Oncesi/Final_Oncesi) Python FAZ tanımıyla eşleşsin diye ikiye ayrılır (term_phases + mevcut Bahar 2027 verisi güncellenir). student_profile.observed_time_window_* artık 4 kaba dilime (ENUM) değil, Python\'un ürettiği ham TIME değerine sahip — final için ayrı bir kolon eklenir; gün bazlı kişiselleştirme için observed_days (JSON) eklenir.';
    }

    public function up(Schema $schema): void
    {
        // term_phases: önce eski+yeni değerleri birlikte kabul eden bir süperset'e genişlet,
        // mevcut Bahar 2027 verisini (vize: 03-27..04-09, final: 05-15..05-28) güncelle,
        // sonra nihai (dar) sete daralt.
        $this->addSql("ALTER TABLE term_phases CHANGE phase phase ENUM('Normal','Sinav_Oncesi','Sinav_Gunu','Ara_Sinav_Oncesi','Final_Oncesi') NOT NULL");
        $this->addSql("UPDATE term_phases SET phase = 'Ara_Sinav_Oncesi' WHERE phase = 'Sinav_Oncesi' AND start_date = '2027-03-27'");
        $this->addSql("UPDATE term_phases SET phase = 'Final_Oncesi' WHERE phase = 'Sinav_Oncesi' AND start_date = '2027-05-15'");
        $this->addSql("ALTER TABLE term_phases CHANGE phase phase ENUM('Normal','Ara_Sinav_Oncesi','Final_Oncesi','Sinav_Gunu') NOT NULL");

        // student_profile: gözlem penceresi kolonları (hepsi bu turda hâlâ NULL — Python henüz
        // yazmadı, veri kaybı riski yok).
        $this->addSql('ALTER TABLE student_profile ADD observed_time_window_ara_sinav_oncesi TIME DEFAULT NULL, ADD observed_time_window_final_oncesi TIME DEFAULT NULL, ADD observed_days JSON DEFAULT NULL, DROP observed_time_window_sinav_oncesi, CHANGE observed_time_window_normal observed_time_window_normal TIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE student_profile ADD observed_time_window_sinav_oncesi ENUM(\'06:00-12:00\', \'12:00-18:00\', \'18:00-24:00\', \'00:00-06:00\') DEFAULT NULL, DROP observed_time_window_ara_sinav_oncesi, DROP observed_time_window_final_oncesi, DROP observed_days, CHANGE observed_time_window_normal observed_time_window_normal ENUM(\'06:00-12:00\', \'12:00-18:00\', \'18:00-24:00\', \'00:00-06:00\') DEFAULT NULL');

        $this->addSql("ALTER TABLE term_phases CHANGE phase phase ENUM('Normal','Sinav_Oncesi','Sinav_Gunu','Ara_Sinav_Oncesi','Final_Oncesi') NOT NULL");
        $this->addSql("UPDATE term_phases SET phase = 'Sinav_Oncesi' WHERE phase = 'Ara_Sinav_Oncesi'");
        $this->addSql("UPDATE term_phases SET phase = 'Sinav_Oncesi' WHERE phase = 'Final_Oncesi'");
        $this->addSql("ALTER TABLE term_phases CHANGE phase phase ENUM('Normal','Sinav_Oncesi','Sinav_Gunu') NOT NULL");
    }
}
