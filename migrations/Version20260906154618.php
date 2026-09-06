<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260906154618 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'conversation_message.ai_visible: false ise mesaj yalnızca teslim/görüldü takibi '
            . 'için tutulur (ör. şablon açılışları), OpenAI geçmişine dahil edilmez. Mevcut '
            . 'satırlar geriye dönük true (1) kabul edilir — hepsi zaten gerçek AI turlarıydı.';
    }

    public function up(Schema $schema): void
    {
        // DEFAULT 1: mevcut satırlar (hepsi gerçek AI turları) geriye dönük ai_visible=true kalsın.
        $this->addSql('ALTER TABLE conversation_message ADD ai_visible TINYINT(1) NOT NULL DEFAULT 1');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE conversation_message DROP ai_visible');
    }
}
