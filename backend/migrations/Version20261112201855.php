<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261112201855 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE group_member (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(10) NOT NULL, invited_via VARCHAR(10) NOT NULL, joined_at DATETIME DEFAULT NULL, group_id INT NOT NULL, user_id INT NOT NULL, UNIQUE INDEX uniq_member_group_user (group_id, user_id), INDEX IDX_A36222A8FE54D947 (group_id), INDEX IDX_A36222A8A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE wichtel_group (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(120) NOT NULL, description LONGTEXT DEFAULT NULL, year SMALLINT NOT NULL, budget_min INT DEFAULT NULL, budget_max INT DEFAULT NULL, currency VARCHAR(3) DEFAULT \'CHF\' NOT NULL, event_date DATE NOT NULL, min_wish_amount SMALLINT NOT NULL, is_drawed TINYINT DEFAULT 0 NOT NULL, drawn_at DATETIME DEFAULT NULL, invite_token VARCHAR(43) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, owner_id INT NOT NULL, UNIQUE INDEX uniq_group_invite_token (invite_token), INDEX IDX_5966CD177E3C61F9 (owner_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE group_member ADD CONSTRAINT FK_A36222A8FE54D947 FOREIGN KEY (group_id) REFERENCES wichtel_group (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE group_member ADD CONSTRAINT FK_A36222A8A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE wichtel_group ADD CONSTRAINT FK_5966CD177E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE group_member DROP FOREIGN KEY FK_A36222A8FE54D947');
        $this->addSql('ALTER TABLE group_member DROP FOREIGN KEY FK_A36222A8A76ED395');
        $this->addSql('ALTER TABLE wichtel_group DROP FOREIGN KEY FK_5966CD177E3C61F9');
        $this->addSql('DROP TABLE group_member');
        $this->addSql('DROP TABLE wichtel_group');
    }
}
