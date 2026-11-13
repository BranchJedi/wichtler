<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261112203735 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE wish (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(160) NOT NULL, description LONGTEXT DEFAULT NULL, url VARCHAR(500) DEFAULT NULL, position SMALLINT NOT NULL, member_id INT NOT NULL, INDEX IDX_D7D174C97597D3FE (member_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE wish ADD CONSTRAINT FK_D7D174C97597D3FE FOREIGN KEY (member_id) REFERENCES group_member (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE wish DROP FOREIGN KEY FK_D7D174C97597D3FE');
        $this->addSql('DROP TABLE wish');
    }
}
