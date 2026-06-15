<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260614000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema: Network and DeviceLease tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE network (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            name VARCHAR(100) NOT NULL,
            subnet VARCHAR(50) NOT NULL,
            dhcp_range_start VARCHAR(50) DEFAULT NULL,
            dhcp_range_end VARCHAR(50) DEFAULT NULL,
            CONSTRAINT uq_network_name UNIQUE (name)
        )');

        $this->addSql('CREATE TABLE device_lease (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            network_id INTEGER DEFAULT NULL,
            mac_address VARCHAR(17) NOT NULL,
            ip_address VARCHAR(15) NOT NULL,
            hostname VARCHAR(255) DEFAULT NULL,
            ip_type VARCHAR(10) NOT NULL,
            lease_expires_at DATETIME DEFAULT NULL,
            seen_at DATETIME NOT NULL,
            last_updated_at DATETIME NOT NULL,
            CONSTRAINT uq_device_lease_mac_seen UNIQUE (mac_address, seen_at),
            CONSTRAINT fk_device_lease_network FOREIGN KEY (network_id) REFERENCES network (id)
        )');

        $this->addSql('CREATE INDEX idx_network ON device_lease (network_id)');
        $this->addSql('CREATE INDEX idx_seen_at ON device_lease (seen_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE device_lease');
        $this->addSql('DROP TABLE network');
    }
}
