<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260615000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename table device_lease to client_device';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE device_lease RENAME TO client_device');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client_device RENAME TO device_lease');
    }
}
