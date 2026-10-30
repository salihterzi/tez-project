<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261028121500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'islenen_webhook_mesaji tablosu: gelen WhatsApp webhook mesajlarının wamid bazlı '
            .'tekrar koruması (2026-10-28). Mevcut koruma `conversation_message.whatsapp_message_id`\'ye '
            .'bakıyordu ama karar ağacı dalı o tabloya hiç yazmıyor — koruma karar ağacı akışı için '
            .'FİİLEN ÖLÜYDÜ ve Meta\'nın aynı mesajı iki kez teslim ettiği durumda giden mesaj da iki '
            .'kez gidiyordu (canlı doğrulandı: 9 ms arayla iki gönderim). wamid PRIMARY KEY olduğu için '
            .'eşzamanlı iki istekte de atomik koruma sağlar; okuyup-sonra-yaz kontrolü yarışa açıktı.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE islenen_webhook_mesaji (
                wamid VARCHAR(255) NOT NULL,
                islendi_at DATETIME NOT NULL,
                PRIMARY KEY (wamid)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
            SQL);
        $this->addSql('CREATE INDEX idx_islenen_webhook_mesaji_islendi_at ON islenen_webhook_mesaji (islendi_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE islenen_webhook_mesaji');
    }
}
