<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261003161750 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'student_profile: öğrencinin saat olarak beyan ettiği mesaj tercihi (preferred_message_time + preference_set_at) ve her faz için gözlenen saatin hangi yöntemle (secenek_a_fallback/secenek_b_bireysel) üretildiğini tutan *_yontem kolonları eklenir (Python henüz yazmıyor, hepsi NULL — veri kaybı riski yok).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE student_profile ADD preferred_message_time TIME DEFAULT NULL, ADD preference_set_at DATETIME DEFAULT NULL, ADD observed_time_window_normal_yontem ENUM('secenek_a_fallback','secenek_b_bireysel') DEFAULT NULL, ADD observed_time_window_ara_sinav_oncesi_yontem ENUM('secenek_a_fallback','secenek_b_bireysel') DEFAULT NULL, ADD observed_time_window_final_oncesi_yontem ENUM('secenek_a_fallback','secenek_b_bireysel') DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE student_profile DROP preferred_message_time, DROP preference_set_at, DROP observed_time_window_normal_yontem, DROP observed_time_window_ara_sinav_oncesi_yontem, DROP observed_time_window_final_oncesi_yontem');
    }
}
