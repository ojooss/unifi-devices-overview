<?php

namespace App\Tests\Service;

use App\Service\DhcpConfigParser;
use PHPUnit\Framework\TestCase;

class DhcpConfigParserTest extends TestCase
{
    private DhcpConfigParser $parser;

    protected function setUp(): void
    {
        $this->parser = new DhcpConfigParser();
    }

    public function testParsesIdentifierToNameAndSubnet(): void
    {
        $result = $this->parser->parse('', 'net_DMZ_br3_192-168-3-0-24');

        $this->assertSame('DMZ', $result['name']);
        $this->assertSame('192.168.3.0/24', $result['subnet']);
    }

    public function testFallbackForUnknownIdentifier(): void
    {
        $result = $this->parser->parse('', 'unknown-format');

        $this->assertSame('unknown-format', $result['name']);
        $this->assertSame('0.0.0.0/0', $result['subnet']);
    }

    public function testParsesFixedMacEntries(): void
    {
        $content = "dhcp-host=set:net_DMZ_br3_192-168-3-0-24,id:*,AA:BB:CC:DD:EE:FF,192.168.3.50\n"
            . "dhcp-host=set:net_DMZ_br3_192-168-3-0-24,id:*,11:22:33:44:55:66,192.168.3.51";

        $result = $this->parser->parse($content, 'net_DMZ_br3_192-168-3-0-24');

        $this->assertSame('192.168.3.50', $result['fixedMacs']['aa:bb:cc:dd:ee:ff']);
        $this->assertSame('192.168.3.51', $result['fixedMacs']['11:22:33:44:55:66']);
    }

    public function testMacKeysAreNormalisedToLowercase(): void
    {
        $result = $this->parser->parse(
            'dhcp-host=set:x,id:*,AA:BB:CC:DD:EE:FF,10.0.0.1',
            'net_X_br0_10-0-0-0-24'
        );

        $this->assertArrayHasKey('aa:bb:cc:dd:ee:ff', $result['fixedMacs']);
    }

    public function testParsesDhcpRange(): void
    {
        $result = $this->parser->parse(
            'dhcp-range=set:net_DMZ_br3_192-168-3-0-24,192.168.3.100,192.168.3.200,24h',
            'net_DMZ_br3_192-168-3-0-24'
        );

        $this->assertSame('192.168.3.100', $result['rangeStart']);
        $this->assertSame('192.168.3.200', $result['rangeEnd']);
    }

    public function testNullRangeWhenMissing(): void
    {
        $result = $this->parser->parse('', 'net_DMZ_br3_192-168-3-0-24');

        $this->assertNull($result['rangeStart']);
        $this->assertNull($result['rangeEnd']);
    }

    public function testEmptyFixedMacsWhenMissing(): void
    {
        $result = $this->parser->parse('', 'net_DMZ_br3_192-168-3-0-24');

        $this->assertSame([], $result['fixedMacs']);
    }
}
