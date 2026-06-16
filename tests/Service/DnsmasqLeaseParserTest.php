<?php

namespace App\Tests\Service;

use App\Service\DnsmasqLeaseParser;
use PHPUnit\Framework\TestCase;

class DnsmasqLeaseParserTest extends TestCase
{
    private DnsmasqLeaseParser $parser;

    protected function setUp(): void
    {
        $this->parser = new DnsmasqLeaseParser();
    }

    public function testParsesStandardLine(): void
    {
        $leases = $this->parser->parse('1781513821 aa:bb:cc:dd:ee:ff 192.168.1.50 my-host 01:aa:bb:cc:dd:ee:ff');

        $this->assertCount(1, $leases);
        $this->assertSame('aa:bb:cc:dd:ee:ff', $leases[0]['mac']);
        $this->assertSame('192.168.1.50', $leases[0]['ip']);
        $this->assertSame('my-host', $leases[0]['hostname']);
        $this->assertInstanceOf(\DateTimeImmutable::class, $leases[0]['leaseExpiresAt']);
        $this->assertSame(1781513821, $leases[0]['leaseExpiresAt']->getTimestamp());
    }

    public function testWildcardHostnameBecomesNull(): void
    {
        $leases = $this->parser->parse('1781513821 aa:bb:cc:dd:ee:ff 192.168.1.50 * 01:aa:bb:cc:dd:ee:ff');

        $this->assertNull($leases[0]['hostname']);
    }

    public function testMacAddressIsLowercased(): void
    {
        $leases = $this->parser->parse('1781513821 AA:BB:CC:DD:EE:FF 192.168.1.50 host *');

        $this->assertSame('aa:bb:cc:dd:ee:ff', $leases[0]['mac']);
    }

    public function testEmptyContentReturnsEmptyArray(): void
    {
        $this->assertSame([], $this->parser->parse(''));
    }

    public function testSkipsLinesWithFewerThanFourParts(): void
    {
        $this->assertSame([], $this->parser->parse('1781513821 aa:bb:cc:dd:ee:ff 192.168.1.50'));
    }

    public function testSkipsBlankLines(): void
    {
        $content = "1781513821 aa:bb:cc:dd:ee:ff 192.168.1.50 host1 *\n\n" .
            "1781511725 11:22:33:44:55:66 192.168.1.51 host2 *";

        $this->assertCount(2, $this->parser->parse($content));
    }

    public function testParsesMultipleLines(): void
    {
        $content = "1781513821 aa:bb:cc:dd:ee:ff 192.168.1.50 host1 *\n" .
            "1781511725 11:22:33:44:55:66 192.168.1.51 host2 *";

        $this->assertCount(2, $this->parser->parse($content));
    }
}
