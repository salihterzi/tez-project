<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260924212759 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'StudentProfile: last_barrier_type_set_at + pending_flow_step ekler; message_frequency_preference ENUM tanımını PHP enum ile eşitler (3/4 eksikti).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE student_profile ADD last_barrier_type_set_at DATETIME DEFAULT NULL, ADD pending_flow_step ENUM(\'ENGEL_BEKLENIYOR\',\'ZAMAN_TAKIBI_BEKLENIYOR\',\'ZAMAN_GUN_SAATI_BEKLENIYOR\',\'ZAMAN_DONUS_SURESI_BEKLENIYOR\',\'ZORLUK_TAKIBI_BEKLENIYOR\',\'MOTIVASYON_TAKIBI_BEKLENIYOR\') DEFAULT NULL, CHANGE message_frequency_preference message_frequency_preference ENUM(\'1\',\'2\',\'3\',\'4\',\'Yalnızca önemli olanlar\') DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE student_profile DROP last_barrier_type_set_at, DROP pending_flow_step, CHANGE message_frequency_preference message_frequency_preference ENUM(\'1\', \'2\', \'Yalnızca önemli olanlar\') DEFAULT NULL');
    }
}
