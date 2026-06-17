<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\ClientDevice;
use App\Entity\Network;
use PHPUnit\Framework\TestCase;

class ClientDeviceTest extends TestCase
{
    public function testConstructorSetsDefaults(): void
    {
        $seenAt = new \DateTimeImmutable('2024-01-01 12:00:00');
        $device = new ClientDevice('aa:bb:cc:dd:ee:ff', $seenAt);

        $this->assertSame('aa:bb:cc:dd:ee:ff', $device->getMacAddress());
        $this->assertSame($seenAt, $device->getSeenAt());
        $this->assertSame('', $device->getIpAddress());
        $this->assertSame('dynamic', $device->getIpType());
        $this->assertNull($device->getHostname());
        $this->assertNull($device->getNetwork());
        $this->assertNull($device->getLeaseExpiresAt());
        $this->assertNull($device->getRemark());
        $this->assertNull($device->getCustomName());
        $this->assertNull($device->getId());
    }

    public function testUpdateChangesAllMutableFields(): void
    {
        $device = new ClientDevice('aa:bb:cc:dd:ee:ff', new \DateTimeImmutable());
        $network = new Network('LAN', '192.168.1.0/24');
        $expiresAt = new \DateTimeImmutable('2024-12-31');
        $now = new \DateTimeImmutable('2024-06-01');
        $seenAt = new \DateTimeImmutable('2024-05-01');

        $device->update($network, '192.168.1.100', 'myhost', 'fixed', $expiresAt, $now, $seenAt);

        $this->assertSame($network, $device->getNetwork());
        $this->assertSame('192.168.1.100', $device->getIpAddress());
        $this->assertSame('myhost', $device->getHostname());
        $this->assertSame('fixed', $device->getIpType());
        $this->assertSame($expiresAt, $device->getLeaseExpiresAt());
        $this->assertSame($now, $device->getLastUpdatedAt());
        $this->assertSame($seenAt, $device->getSeenAt());
    }

    public function testUpdateAcceptsNullableValues(): void
    {
        $device = new ClientDevice('aa:bb:cc:dd:ee:ff', new \DateTimeImmutable());
        $device->update(null, '10.0.0.1', null, 'dynamic', null, new \DateTimeImmutable(), new \DateTimeImmutable());

        $this->assertNull($device->getNetwork());
        $this->assertNull($device->getHostname());
        $this->assertNull($device->getLeaseExpiresAt());
    }

    public function testUpdateDoesNotTouchRemarkOrCustomName(): void
    {
        $device = new ClientDevice('aa:bb:cc:dd:ee:ff', new \DateTimeImmutable());
        $device->setRemark('Im Schrank');
        $device->setCustomName('Mein NAS');

        $device->update(null, '10.0.0.2', 'nas', 'fixed', null, new \DateTimeImmutable(), new \DateTimeImmutable());

        $this->assertSame('Im Schrank', $device->getRemark());
        $this->assertSame('Mein NAS', $device->getCustomName());
    }

    public function testSetRemark(): void
    {
        $device = new ClientDevice('aa:bb:cc:dd:ee:ff', new \DateTimeImmutable());
        $device->setRemark('Drucker im Keller');
        $this->assertSame('Drucker im Keller', $device->getRemark());
    }

    public function testSetRemarkToNull(): void
    {
        $device = new ClientDevice('aa:bb:cc:dd:ee:ff', new \DateTimeImmutable());
        $device->setRemark('alt');
        $device->setRemark(null);
        $this->assertNull($device->getRemark());
    }

    public function testSetCustomName(): void
    {
        $device = new ClientDevice('aa:bb:cc:dd:ee:ff', new \DateTimeImmutable());
        $device->setCustomName('Mein NAS');
        $this->assertSame('Mein NAS', $device->getCustomName());
    }

    public function testSetCustomNameToNull(): void
    {
        $device = new ClientDevice('aa:bb:cc:dd:ee:ff', new \DateTimeImmutable());
        $device->setCustomName('Mein NAS');
        $device->setCustomName(null);
        $this->assertNull($device->getCustomName());
    }
}
