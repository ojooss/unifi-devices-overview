<?php

namespace App\Tests\Service;

use App\Service\TopologyParser;
use PHPUnit\Framework\TestCase;

class TopologyParserTest extends TestCase
{
    private TopologyParser $parser;

    protected function setUp(): void
    {
        $this->parser = new TopologyParser();
    }

    public function testParsesClientAliases(): void
    {
        $json = json_encode([
            'default' => [
                'vertices' => [
                    ['mac' => 'AA:BB:CC:DD:EE:FF', 'name' => 'My Printer', 'type' => 'CLIENT', 'unifiDevice' => false],
                    ['mac' => '11:22:33:44:55:66', 'name' => 'Smart TV', 'type' => 'CLIENT', 'unifiDevice' => false],
                ],
                'edges' => [],
            ],
        ]);

        $result = $this->parser->parse($json);

        $this->assertSame(['aa:bb:cc:dd:ee:ff' => 'My Printer', '11:22:33:44:55:66' => 'Smart TV'], $result);
    }

    public function testMacIsNormalizedToLowercase(): void
    {
        $json = json_encode([
            'default' => [
                'vertices' => [
                    ['mac' => 'AA:BB:CC:DD:EE:FF', 'name' => 'Printer'],
                ],
            ],
        ]);

        $result = $this->parser->parse($json);

        $this->assertArrayHasKey('aa:bb:cc:dd:ee:ff', $result);
    }

    public function testSkipsEntriesWithEmptyName(): void
    {
        $json = json_encode([
            'default' => [
                'vertices' => [
                    ['mac' => 'aa:bb:cc:dd:ee:ff', 'name' => ''],
                    ['mac' => '11:22:33:44:55:66', 'name' => '   '],
                ],
            ],
        ]);

        $result = $this->parser->parse($json);

        $this->assertEmpty($result);
    }

    public function testSkipsEntriesWithoutMacOrName(): void
    {
        $json = json_encode([
            'default' => [
                'vertices' => [
                    ['name' => 'No MAC here'],
                    ['mac' => 'aa:bb:cc:dd:ee:ff'],
                ],
            ],
        ]);

        $result = $this->parser->parse($json);

        $this->assertEmpty($result);
    }

    public function testStripsUnifiMacSuffix(): void
    {
        $json = json_encode([
            'default' => [
                'vertices' => [
                    ['mac' => 'ce:b9:b2:a5:2c:f5', 'name' => 'Tab-S9-FE-von-Oliver 2c:f5'],
                    ['mac' => '44:87:63:cc:66:ec', 'name' => 'Navimow_i 66:ec'],
                    ['mac' => '78:be:81:08:bb:63', 'name' => 'Centauri-Carbon bb:63'],
                    ['mac' => 'aa:bb:cc:dd:ee:ff', 'name' => 'Normal Name'],
                ],
            ],
        ]);

        $result = $this->parser->parse($json);

        $this->assertSame('Tab-S9-FE-von-Oliver', $result['ce:b9:b2:a5:2c:f5']);
        $this->assertSame('Navimow_i', $result['44:87:63:cc:66:ec']);
        $this->assertSame('Centauri-Carbon', $result['78:be:81:08:bb:63']);
        $this->assertSame('Normal Name', $result['aa:bb:cc:dd:ee:ff']);
    }

    public function testReturnsEmptyOnInvalidJson(): void
    {
        $result = $this->parser->parse('not valid json');

        $this->assertEmpty($result);
    }

    public function testHandlesMultipleSites(): void
    {
        $json = json_encode([
            'site1' => [
                'vertices' => [
                    ['mac' => 'aa:bb:cc:dd:ee:ff', 'name' => 'Device A'],
                ],
            ],
            'site2' => [
                'vertices' => [
                    ['mac' => '11:22:33:44:55:66', 'name' => 'Device B'],
                ],
            ],
        ]);

        $result = $this->parser->parse($json);

        $this->assertCount(2, $result);
        $this->assertSame('Device A', $result['aa:bb:cc:dd:ee:ff']);
        $this->assertSame('Device B', $result['11:22:33:44:55:66']);
    }
}
