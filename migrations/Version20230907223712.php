<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230907223712 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE friends_request (id INT AUTO_INCREMENT NOT NULL, sender_id INT DEFAULT NULL, receiver_id INT DEFAULT NULL, accepted TINYINT(1) NOT NULL, INDEX IDX_BCFC791FF624B39D (sender_id), INDEX IDX_BCFC791FCD53EDB6 (receiver_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE friends_request ADD CONSTRAINT FK_BCFC791FF624B39D FOREIGN KEY (sender_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE friends_request ADD CONSTRAINT FK_BCFC791FCD53EDB6 FOREIGN KEY (receiver_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE user ADD username VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE friends_request DROP FOREIGN KEY FK_BCFC791FF624B39D');
        $this->addSql('ALTER TABLE friends_request DROP FOREIGN KEY FK_BCFC791FCD53EDB6');
        $this->addSql('DROP TABLE friends_request');
        $this->addSql('ALTER TABLE `user` DROP username');
    }
}
