<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Network;
use PHPUnit\Framework\TestCase;

class NetworkTest extends TestCase
{
    public function testConstructorSetsNameAndSubnet(): void
    {
        $network = new Network('LAN', '192.168.1.0/24');

        $this->assertSame('LAN', $network->getName());
        $this->assertSame('192.168.1.0/24', $network->getSubnet());
        $this->assertNull($network->getDhcpRangeStart());
        $this->assertNull($network->getDhcpRangeEnd());
        $this->assertNull($network->getId());
        $this->assertCount(0, $network->getClientDevices());
    }

    public function testSetSubnet(): void
    {
        $network = new Network('LAN', '192.168.1.0/24');
        $network->setSubnet('10.0.0.0/8');
        $this->assertSame('10.0.0.0/8', $network->getSubnet());
    }

    public function testSetDhcpRange(): void
    {
        $network = new Network('LAN', '192.168.1.0/24');
        $network->setDhcpRange('192.168.1.100', '192.168.1.200');

        $this->assertSame('192.168.1.100', $network->getDhcpRangeStart());
        $this->assertSame('192.168.1.200', $network->getDhcpRangeEnd());
    }

    public function testClearDhcpRange(): void
    {
        $network = new Network('LAN', '192.168.1.0/24');
        $network->setDhcpRange('192.168.1.100', '192.168.1.200');
        $network->setDhcpRange(null, null);

        $this->assertNull($network->getDhcpRangeStart());
        $this->assertNull($network->getDhcpRangeEnd());
    }
}
