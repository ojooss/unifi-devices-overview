<?php

namespace App\Tests\Controller;

use App\Service\UnifiApiClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class ScanControllerTest extends AbstractControllerTest
{
    /** @param array<string, mixed>|list<mixed> $payload */
    private function jsonResponse(array $payload, int $status = 200): MockResponse
    {
        return new MockResponse(json_encode($payload, JSON_THROW_ON_ERROR), [
            'http_code' => $status,
            'response_headers' => ['Content-Type: application/json'],
        ]);
    }

    public function testScanWithoutConfigurationShowsError(): void
    {
        $client = static::createClient();
        $client->request('POST', '/scan');

        $this->assertResponseRedirects('/');
        $client->followRedirect();
        $content = $client->getResponse()->getContent();
        $this->assertStringContainsString('not configured', $content);
        $this->assertStringNotContainsString('action="/scan"', $content);
    }

    public function testScanImportsDevices(): void
    {
        $client = static::createClient();
        // Keep the container (and the replaced API client) across the redirect.
        $client->disableReboot();
        static::getContainer()->set(UnifiApiClient::class, new UnifiApiClient(
            new MockHttpClient([
                $this->jsonResponse(['data' => [
                    ['mac' => 'aa:bb:cc:dd:ee:01', 'name' => 'Scanned Device', 'last_ip' => '192.168.3.10',
                     'last_seen' => 1794563159, 'last_connection_network_name' => 'DMZ'],
                ]]),
                $this->jsonResponse(['data' => [['name' => 'DMZ', 'ip_subnet' => '192.168.3.1/24']]]),
            ]),
            'https://udm.example',
            'key',
        ));

        $client->request('POST', '/scan');
        $this->assertResponseRedirects('/');

        $client->followRedirect();
        $content = $client->getResponse()->getContent();
        $this->assertStringContainsString('Scan finished', $content);
        $this->assertStringContainsString('1 new device', $content);
        $this->assertStringContainsString('aa:bb:cc:dd:ee:01', $content);
        $this->assertStringContainsString('Scanned Device', $content);
        $this->assertStringContainsString('action="/scan"', $content);
    }

    public function testScanFailureShowsErrorAndKeepsOverviewUsable(): void
    {
        $client = static::createClient();
        static::getContainer()->set(UnifiApiClient::class, new UnifiApiClient(
            new MockHttpClient($this->jsonResponse([], 401)),
            'https://udm.example',
            'wrong-key',
        ));

        $client->request('POST', '/scan');
        $client->followRedirect();

        $this->assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent();
        $this->assertStringContainsString('Scan failed', $content);
        $this->assertStringNotContainsString('wrong-key', $content);
    }

    public function testScanRouteRejectsGet(): void
    {
        $client = static::createClient();
        $client->request('GET', '/scan');

        $this->assertResponseStatusCodeSame(405);
    }
}
