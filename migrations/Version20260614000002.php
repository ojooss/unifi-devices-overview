<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260614000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add custom_name column to device_lease';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE device_lease ADD COLUMN custom_name VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE device_lease_tmp (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            network_id INTEGER DEFAULT NULL,
            mac_address VARCHAR(17) NOT NULL,
            ip_address VARCHAR(15) NOT NULL,
            hostname VARCHAR(255) DEFAULT NULL,
            ip_type VARCHAR(10) NOT NULL,
            lease_expires_at DATETIME DEFAULT NULL,
            seen_at DATETIME NOT NULL,
            last_updated_at DATETIME NOT NULL,
            remark CLOB DEFAULT NULL,
            CONSTRAINT uq_device_lease_mac_seen UNIQUE (mac_address, seen_at),
            CONSTRAINT fk_device_lease_network FOREIGN KEY (network_id) REFERENCES network (id)
        )');
        $this->addSql('INSERT INTO device_lease_tmp SELECT id, network_id, mac_address, ip_address, hostname, ip_type, lease_expires_at, seen_at, last_updated_at, remark FROM device_lease');
        $this->addSql('DROP TABLE device_lease');
        $this->addSql('ALTER TABLE device_lease_tmp RENAME TO device_lease');
        $this->addSql('CREATE INDEX idx_network ON device_lease (network_id)');
        $this->addSql('CREATE INDEX idx_seen_at ON device_lease (seen_at)');
    }
}
