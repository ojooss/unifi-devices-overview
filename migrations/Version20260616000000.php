<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260616000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add unifi_alias column to client_device';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client_device ADD COLUMN unifi_alias VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client_device DROP COLUMN unifi_alias');
    }
}
