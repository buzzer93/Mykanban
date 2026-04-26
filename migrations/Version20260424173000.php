<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260424173000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add unique username to user and use it as login identifier';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD username VARCHAR(50) DEFAULT NULL');
        $this->addSql('UPDATE `user` SET username = SUBSTRING_INDEX(email, "@", 1) WHERE username IS NULL OR username = ""');
        $this->addSql('ALTER TABLE `user` CHANGE username username VARCHAR(50) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649F85E0677 ON `user` (username)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_8D93D649F85E0677 ON `user`');
        $this->addSql('ALTER TABLE `user` DROP username');
    }
}
