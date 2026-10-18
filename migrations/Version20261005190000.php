<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * `student_profile.message_frequency_preference` kolonunu kaldırır — kullanıcı kararı
 * (2026-10-05): haftalık kişiselleştirilmiş mesaj sıklığı artık herkes için tek sabit üst
 * sınıra düşer (bkz. {@see \App\Service\MessageFrequencyPolicy}), hangi GÜNLERDE mesaj
 * gönderileceğini zaten `observed_days`/`MessageDispatchScheduler` belirliyor — ayrı bir
 * öğrenci tercihine gerek yok. YBS kayıt anketinin eski Soru 4'ü ("Haftada kaç mesaj almak
 * size uygun olur?") bu alanın TEK gerçek yazıcısıydı ama hiçbir import pipeline'ı onu hiç
 * doldurmamıştı (`EngagementFlowService` M1 akışında sabit `Bir` yazıyordu, bkz. proje
 * notları) — kayıpsız kaldırılabilir. Soru 4, YBS formunda "Günün hangi zamanında ders
 * çalışırsınız?" (Sabah/Akşam/Gece/Değişken) olarak değiştirildi; cevabı artık mevcut
 * `preferred_study_time`/`preferred_message_time` alanlarına yazılacak (aynı alanlar M1
 * akışındaki "zaman" mikro sorusunun da yazdığı alanlar).
 */
final class Version20261005190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'student_profile.message_frequency_preference kolonu kaldırılır (YBS Soru 4 artık preferred_study_time\'ı besliyor).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE student_profile DROP message_frequency_preference');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE student_profile ADD message_frequency_preference ENUM('1','2','Çalışacağım günlerin her biri','Yalnızca önemli olanlar') DEFAULT NULL");
    }
}
