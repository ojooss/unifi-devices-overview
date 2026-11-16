<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260618000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add web_ports to client_device (open ports 80/8080/443 found by the UniFi scan)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client_device ADD COLUMN web_ports CLOB DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client_device DROP COLUMN web_ports');
    }
}
