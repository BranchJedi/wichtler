<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261113062651 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE assignment (id INT AUTO_INCREMENT NOT NULL, group_id INT NOT NULL, giver_id INT NOT NULL, receiver_id INT NOT NULL, UNIQUE INDEX uniq_assignment_giver (group_id, giver_id), UNIQUE INDEX uniq_assignment_receiver (group_id, receiver_id), INDEX IDX_30C544BAFE54D947 (group_id), INDEX IDX_30C544BA75BD1D29 (giver_id), INDEX IDX_30C544BACD53EDB6 (receiver_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE exclusion (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, group_id INT NOT NULL, member_id INT NOT NULL, excluded_member_id INT NOT NULL, UNIQUE INDEX uniq_exclusion_pair (member_id, excluded_member_id), INDEX IDX_DF1686CFE54D947 (group_id), INDEX IDX_DF1686C7597D3FE (member_id), INDEX IDX_DF1686CDA37BBB2 (excluded_member_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE assignment ADD CONSTRAINT FK_30C544BAFE54D947 FOREIGN KEY (group_id) REFERENCES wichtel_group (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE assignment ADD CONSTRAINT FK_30C544BA75BD1D29 FOREIGN KEY (giver_id) REFERENCES group_member (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE assignment ADD CONSTRAINT FK_30C544BACD53EDB6 FOREIGN KEY (receiver_id) REFERENCES group_member (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE exclusion ADD CONSTRAINT FK_DF1686CFE54D947 FOREIGN KEY (group_id) REFERENCES wichtel_group (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE exclusion ADD CONSTRAINT FK_DF1686C7597D3FE FOREIGN KEY (member_id) REFERENCES group_member (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE exclusion ADD CONSTRAINT FK_DF1686CDA37BBB2 FOREIGN KEY (excluded_member_id) REFERENCES group_member (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE group_member ADD exclusions_pruned TINYINT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE assignment DROP FOREIGN KEY FK_30C544BAFE54D947');
        $this->addSql('ALTER TABLE assignment DROP FOREIGN KEY FK_30C544BA75BD1D29');
        $this->addSql('ALTER TABLE assignment DROP FOREIGN KEY FK_30C544BACD53EDB6');
        $this->addSql('ALTER TABLE exclusion DROP FOREIGN KEY FK_DF1686CFE54D947');
        $this->addSql('ALTER TABLE exclusion DROP FOREIGN KEY FK_DF1686C7597D3FE');
        $this->addSql('ALTER TABLE exclusion DROP FOREIGN KEY FK_DF1686CDA37BBB2');
        $this->addSql('DROP TABLE assignment');
        $this->addSql('DROP TABLE exclusion');
        $this->addSql('ALTER TABLE group_member DROP exclusions_pruned');
    }
}
