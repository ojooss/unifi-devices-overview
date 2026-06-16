<?php

namespace App\Tests\Controller;

use App\Entity\ClientDevice;
use Doctrine\ORM\EntityManagerInterface;

class CsvExportControllerTest extends AbstractControllerTest
{
    protected function loadFixtures(EntityManagerInterface $em): void
    {
        $device = new ClientDevice('aa:bb:cc:dd:ee:ff', new \DateTimeImmutable('2024-01-01 00:00:00'));
        $now = new \DateTimeImmutable('2024-06-01 12:00:00');
        $device->update(null, '192.168.1.100', 'testhost', 'dynamic', null, $now, 'Drucker Büro');
        $device->setCustomName('My Device');
        $device->setRemark('some remark');
        $em->persist($device);

        $device2 = new ClientDevice('11:22:33:44:55:66', new \DateTimeImmutable('2024-01-01 00:00:00'));
        $device2->update(null, '10.0.0.5', 'fixedhost', 'fixed', null, new \DateTimeImmutable('2024-06-01 12:00:00'));
        $em->persist($device2);
    }

    public function testCsvResponseHeaders(): void
    {
        $client = static::createClient();
        $client->request('GET', '/export/csv');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('text/csv', $client->getResponse()->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $client->getResponse()->headers->get('Content-Disposition'));
        $this->assertStringContainsString('devices.csv', $client->getResponse()->headers->get('Content-Disposition'));
    }

    public function testCsvContainsHeaderRow(): void
    {
        $client = static::createClient();
        $client->request('GET', '/export/csv');

        $content = $client->getResponse()->getContent();
        $this->assertStringContainsString('MAC Address', $content);
        $this->assertStringContainsString('IP Address', $content);
        $this->assertStringContainsString('Hostname', $content);
        $this->assertStringContainsString('UniFi Alias', $content);
    }

    public function testCsvContainsDeviceData(): void
    {
        $client = static::createClient();
        $client->request('GET', '/export/csv');

        $content = $client->getResponse()->getContent();
        $this->assertStringContainsString('aa:bb:cc:dd:ee:ff', $content);
        $this->assertStringContainsString('192.168.1.100', $content);
        $this->assertStringContainsString('My Device', $content);
        $this->assertStringContainsString('Drucker Büro', $content);
        $this->assertStringContainsString('some remark', $content);
        $this->assertStringContainsString('11:22:33:44:55:66', $content);
    }

    public function testCsvFilterByType(): void
    {
        $client = static::createClient();
        $client->request('GET', '/export/csv', ['type' => 'fixed']);

        $content = $client->getResponse()->getContent();
        $this->assertStringContainsString('11:22:33:44:55:66', $content);
        $this->assertStringNotContainsString('aa:bb:cc:dd:ee:ff', $content);
    }
}
