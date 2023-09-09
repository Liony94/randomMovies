<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230909202257 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE serie (id INT AUTO_INCREMENT NOT NULL, serie_db_id INT DEFAULT NULL, title VARCHAR(255) DEFAULT NULL, poster_path VARCHAR(255) DEFAULT NULL, description LONGTEXT DEFAULT NULL, trailer_url VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE serie_user (serie_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_56F6C27BD94388BD (serie_id), INDEX IDX_56F6C27BA76ED395 (user_id), PRIMARY KEY(serie_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE serie_user_dislike (serie_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_5453CEDCD94388BD (serie_id), INDEX IDX_5453CEDCA76ED395 (user_id), PRIMARY KEY(serie_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE serie_user ADD CONSTRAINT FK_56F6C27BD94388BD FOREIGN KEY (serie_id) REFERENCES serie (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE serie_user ADD CONSTRAINT FK_56F6C27BA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE serie_user_dislike ADD CONSTRAINT FK_5453CEDCD94388BD FOREIGN KEY (serie_id) REFERENCES serie (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE serie_user_dislike ADD CONSTRAINT FK_5453CEDCA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE serie_user DROP FOREIGN KEY FK_56F6C27BD94388BD');
        $this->addSql('ALTER TABLE serie_user DROP FOREIGN KEY FK_56F6C27BA76ED395');
        $this->addSql('ALTER TABLE serie_user_dislike DROP FOREIGN KEY FK_5453CEDCD94388BD');
        $this->addSql('ALTER TABLE serie_user_dislike DROP FOREIGN KEY FK_5453CEDCA76ED395');
        $this->addSql('DROP TABLE serie');
        $this->addSql('DROP TABLE serie_user');
        $this->addSql('DROP TABLE serie_user_dislike');
    }
}
