<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261009114539 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'scheduled_message: nullable sent_message_log_id eklenir — hatırlatma (kind=reminder) kaydının hangi SentMessageLog satırını kovaladığı artık zamanlama anında donduruluyor, dispatch anında "en son kayıt" yeniden aranmıyor.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE scheduled_message ADD sent_message_log_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE scheduled_message DROP sent_message_log_id');
    }
}
