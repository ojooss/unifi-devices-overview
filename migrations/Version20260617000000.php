<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260617000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace (mac_address, seen_at) unique constraint with mac_address-only unique constraint';
    }

    public function up(Schema $schema): void
    {
        // Keep the oldest row per MAC (lowest id), delete all newer duplicates
        $this->addSql('DELETE FROM client_device WHERE id NOT IN (SELECT MIN(id) FROM client_device GROUP BY mac_address)');
        $this->addSql('DROP INDEX IF EXISTS uq_client_device_mac_seen');
        $this->addSql('CREATE UNIQUE INDEX uq_client_device_mac ON client_device (mac_address)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS uq_client_device_mac');
        $this->addSql('CREATE UNIQUE INDEX uq_client_device_mac_seen ON client_device (mac_address, seen_at)');
    }
}
