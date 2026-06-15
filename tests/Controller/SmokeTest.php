<?php

namespace App\Tests\Controller;

class SmokeTest extends AbstractControllerTest
{
    public function testOverviewLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Device Overview', $client->getResponse()->getContent());
    }

    public function testOverviewShowsEmptyStateWithNoData(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('No data available yet.', $client->getResponse()->getContent());
    }

    public function testUploadFormLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/upload');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Import Support File', $client->getResponse()->getContent());
        $this->assertStringContainsString('<form', $client->getResponse()->getContent());
    }

    public function testUnknownRouteReturns404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/does-not-exist');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testGetMethodOnUploadPostRouteReturnsOk(): void
    {
        $client = static::createClient();
        $client->request('GET', '/upload');

        $this->assertResponseIsSuccessful();
    }
}
