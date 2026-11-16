<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\UnifiApiClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class UnifiApiClientTest extends TestCase
{
    private function makeClient(MockResponse $response, string $apiKey = 'secret-key'): UnifiApiClient
    {
        return new UnifiApiClient(new MockHttpClient($response), 'https://udm.example', $apiKey, 'default', false);
    }

    /** @param array<string, mixed>|list<mixed> $payload */
    private function json(array $payload, int $status = 200): MockResponse
    {
        return new MockResponse(json_encode($payload, JSON_THROW_ON_ERROR), [
            'http_code' => $status,
            'response_headers' => ['Content-Type: application/json'],
        ]);
    }

    public function testIsConfiguredRequiresBaseUrlAndKey(): void
    {
        $http = new MockHttpClient();

        $this->assertTrue(new UnifiApiClient($http, 'https://udm.example', 'key')->isConfigured());
        $this->assertFalse(new UnifiApiClient($http, 'https://udm.example', '')->isConfigured());
        $this->assertFalse(new UnifiApiClient($http, '', 'key')->isConfigured());
    }

    public function testFetchClientsNormalizesRows(): void
    {
        $client = $this->makeClient($this->json(['meta' => ['rc' => 'ok'], 'data' => [
            [
                'mac' => 'CC:F7:35:E8:56:C8',
                'name' => ' Steckdose ',
                'hostname' => 'AmazonPlug',
                'last_ip' => '192.168.3.150',
                'use_fixedip' => true,
                'last_seen' => 1794563159,
                'last_connection_network_name' => 'DMZ',
            ],
            ['mac' => '38:22:e2:1f:46:95', 'last_ip' => 'fe80::1', 'name' => '', 'use_fixedip' => false],
            ['name' => 'no mac'],
        ]]));

        $clients = $client->fetchClients();

        $this->assertCount(2, $clients);
        $this->assertSame('cc:f7:35:e8:56:c8', $clients[0]['mac']);
        $this->assertSame('Steckdose', $clients[0]['name']);
        $this->assertSame('AmazonPlug', $clients[0]['hostname']);
        $this->assertSame('192.168.3.150', $clients[0]['ip']);
        $this->assertTrue($clients[0]['fixed']);
        $this->assertSame(1794563159, $clients[0]['seenAt']->getTimestamp());
        $this->assertSame('DMZ', $clients[0]['network']);

        // IPv6 and empty names are dropped.
        $this->assertSame('', $clients[1]['ip']);
        $this->assertNull($clients[1]['name']);
        $this->assertFalse($clients[1]['fixed']);
        $this->assertNull($clients[1]['network']);
    }

    public function testFetchClientsSendsApiKeyHeaderToClassicEndpoint(): void
    {
        $captured = [];
        $callback = function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = ['method' => $method, 'url' => $url, 'headers' => $options['headers']];

            return $this->json(['data' => []]);
        };
        $http = new MockHttpClient($callback);

        new UnifiApiClient($http, 'https://udm.example/', 'secret-key', 'default')->fetchClients();

        $this->assertSame('GET', $captured['method']);
        $this->assertSame('https://udm.example/proxy/network/api/s/default/rest/user', $captured['url']);
        $this->assertContains('X-API-KEY: secret-key', $captured['headers']);
    }

    public function testFetchNetworksConvertsGatewayAddressToNetworkAddress(): void
    {
        $client = $this->makeClient($this->json(['data' => [
            [
                'name' => 'DMZ',
                'ip_subnet' => '192.168.3.1/24',
                'dhcpd_enabled' => true,
                'dhcpd_start' => '192.168.3.6',
                'dhcpd_stop' => '192.168.3.254',
            ],
            ['name' => 'Guest', 'ip_subnet' => '10.0.77.1/20', 'dhcpd_enabled' => false],
            ['name' => 'WAN'],
        ]]));

        $networks = $client->fetchNetworks();

        $this->assertCount(2, $networks);
        $this->assertSame('192.168.3.0/24', $networks[0]['subnet']);
        $this->assertSame('192.168.3.6', $networks[0]['rangeStart']);
        $this->assertSame('192.168.3.254', $networks[0]['rangeEnd']);
        $this->assertSame('10.0.64.0/20', $networks[1]['subnet']);
        $this->assertNull($networks[1]['rangeStart']);
    }

    public function testSubnetRejectsInvalidValues(): void
    {
        $client = $this->makeClient($this->json(['data' => []]));

        $this->assertNull($client->subnet(null));
        $this->assertNull($client->subnet('192.168.3.1'));
        $this->assertNull($client->subnet('192.168.3.1/33'));
        $this->assertNull($client->subnet('not-an-ip/24'));
    }

    public function testRejectedKeyThrowsWithoutLeakingTheKey(): void
    {
        $client = $this->makeClient($this->json(['meta' => ['rc' => 'error']], 401));

        try {
            $client->fetchClients();
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('401', $e->getMessage());
            $this->assertStringNotContainsString('secret-key', $e->getMessage());
        }
    }

    public function testServerErrorThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('HTTP 500');

        $this->makeClient($this->json([], 500))->fetchClients();
    }

    public function testUnexpectedPayloadThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Unexpected UniFi API response');

        $this->makeClient($this->json(['meta' => ['rc' => 'ok']]))->fetchClients();
    }

    public function testNotConfiguredThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('not configured');

        new UnifiApiClient(new MockHttpClient(), '', '')->fetchClients();
    }
}
