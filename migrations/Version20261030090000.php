<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261030090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'student_profile.oturum_kaynagi (GERİ) + oturumda_sayi_yazildi (2026-10-30, '
            .'kullanıcı kararı). (1) `oturum_kaynagi`: oturumu BAŞLATAN proaktif mesajın türü '
            .'(ScheduledMessageKind). 28 Ekim\'de eklenip 29 Ekim\'de kaldırılmıştı — URL butonu '
            .'webhook\'a sinyal göndermediği için "yanıt beklenmiyor" sayılmıştı; ama `_v3` '
            .'gövdesi hâlâ serbest metne davet ediyor ("1-7 arası bir sayı yazman yeterli"), '
            .'dolayısıyla SAYI OLMAYAN serbest metin yanlış dala ("akış tamamlandı") düşüyordu. '
            .'`pending_flow_step` bu iş için kullanılamaz (2026-10-19: M1\'i sessizce bloke etme '
            .'riski). (2) `oturumda_sayi_yazildi`: haftalık gün sayısı tercihi OTURUMDA BİR KEZ '
            .'güncellenebilir — ikinci sayı ayarı değiştirmez, bildirim dalına düşer.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE student_profile ADD oturum_kaynagi VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE student_profile ADD oturumda_sayi_yazildi TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE student_profile DROP oturumda_sayi_yazildi');
        $this->addSql('ALTER TABLE student_profile DROP oturum_kaynagi');
    }
}
