<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260916201610 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ogrenci ADD calisma_saati_baslangic TIME DEFAULT NULL, ADD calisma_saati_bitis TIME DEFAULT NULL, ADD aile_sorumlulugu TINYINT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ogrenci DROP calisma_saati_baslangic, DROP calisma_saati_bitis, DROP aile_sorumlulugu');
    }
}
