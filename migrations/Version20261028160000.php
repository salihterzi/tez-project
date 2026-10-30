<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261028160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'student_profile.oturum_kaynagi (2026-10-28, kullanıcı kararı): oturumu BAŞLATAN '
            .'proaktif mesajın türü. Günlük kontrol kanalı (düz/övgü) bilerek pending_flow_step '
            .'SET ETMİYOR, ama tek butonu ("E-kampüs\'e git") basılması beklenen bir buton — '
            .'öğrenci onun yerine serbest metin yazarsa "seçeneklerden birini seçin" uyarısı '
            .'gitmeli, "akış tamamlandı" bilgisi değil. pending_flow_step kullanılamadığı için '
            .'(M1\'i sessizce bloke etme riski, 2026-10-19 kararı) oturum kaynağı ayrı tutulur.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE student_profile ADD oturum_kaynagi VARCHAR(32) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE student_profile DROP oturum_kaynagi');
    }
}
