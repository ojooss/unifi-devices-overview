<?php

namespace App\Tests\Controller;

use App\Entity\ClientDevice;
use Doctrine\ORM\EntityManagerInterface;

class LeaseEditControllerTest extends AbstractControllerTest
{
    private int $leaseId;

    protected function loadFixtures(EntityManagerInterface $em): void
    {
        $lease = new ClientDevice('aa:bb:cc:dd:ee:ff', new \DateTimeImmutable('2024-01-01 00:00:00'));
        $lease->update(null, '192.168.1.100', 'testhost', 'dynamic', null, new \DateTimeImmutable());
        $em->persist($lease);
        $em->flush();
        $this->leaseId = $lease->getId();
    }

    public function testSaveRemarkRedirects(): void
    {
        $client = static::createClient();
        $client->request('POST', '/lease/' . $this->leaseId . '/remark', [
            'remark'  => 'Drucker im Keller',
            '_return' => '/',
        ]);

        $this->assertResponseRedirects('/');
    }

    public function testSaveRemarkPersists(): void
    {
        $client = static::createClient();
        $client->request('POST', '/lease/' . $this->leaseId . '/remark', [
            'remark'  => 'Drucker im Keller',
            '_return' => '/',
        ]);

        $em = static::getContainer()->get('doctrine')->getManager();
        $em->clear();
        $lease = $em->find(ClientDevice::class, $this->leaseId);

        $this->assertSame('Drucker im Keller', $lease->getRemark());
    }

    public function testEmptyRemarkClearsExistingValue(): void
    {
        // Vorher setzen
        $kernel = static::bootKernel();
        $em = static::getContainer()->get('doctrine')->getManager();
        $em->find(ClientDevice::class, $this->leaseId)->setRemark('alt');
        $em->flush();
        static::ensureKernelShutdown();

        $client = static::createClient();
        $client->request('POST', '/lease/' . $this->leaseId . '/remark', [
            'remark'  => '',
            '_return' => '/',
        ]);

        $em = static::getContainer()->get('doctrine')->getManager();
        $em->clear();
        $this->assertNull($em->find(ClientDevice::class, $this->leaseId)->getRemark());
    }

    public function testSaveCustomNameRedirects(): void
    {
        $client = static::createClient();
        $client->request('POST', '/lease/' . $this->leaseId . '/name', [
            'custom_name' => 'Mein NAS',
            '_return'     => '/',
        ]);

        $this->assertResponseRedirects('/');
    }

    public function testSaveCustomNamePersists(): void
    {
        $client = static::createClient();
        $client->request('POST', '/lease/' . $this->leaseId . '/name', [
            'custom_name' => 'Mein Router',
            '_return'     => '/',
        ]);

        $em = static::getContainer()->get('doctrine')->getManager();
        $em->clear();
        $lease = $em->find(ClientDevice::class, $this->leaseId);

        $this->assertSame('Mein Router', $lease->getCustomName());
    }

    public function testEmptyCustomNameClearsExistingValue(): void
    {
        $kernel = static::bootKernel();
        $em = static::getContainer()->get('doctrine')->getManager();
        $em->find(ClientDevice::class, $this->leaseId)->setCustomName('alt');
        $em->flush();
        static::ensureKernelShutdown();

        $client = static::createClient();
        $client->request('POST', '/lease/' . $this->leaseId . '/name', [
            'custom_name' => '   ',
            '_return'     => '/',
        ]);

        $em = static::getContainer()->get('doctrine')->getManager();
        $em->clear();
        $this->assertNull($em->find(ClientDevice::class, $this->leaseId)->getCustomName());
    }

    public function testRemarkVisibleOnOverview(): void
    {
        $client = static::createClient();
        $client->request('POST', '/lease/' . $this->leaseId . '/remark', [
            'remark'  => 'Sichtbare Bemerkung',
            '_return' => '/',
        ]);
        $client->followRedirect();

        $client->request('GET', '/');
        $this->assertStringContainsString('Sichtbare Bemerkung', $client->getResponse()->getContent());
    }

    public function testCustomNameVisibleOnOverview(): void
    {
        $client = static::createClient();
        $client->request('POST', '/lease/' . $this->leaseId . '/name', [
            'custom_name' => 'Sichtbarer Name',
            '_return'     => '/',
        ]);

        $client->request('GET', '/');
        $this->assertStringContainsString('Sichtbarer Name', $client->getResponse()->getContent());
    }

    public function testDeleteRedirects(): void
    {
        $client = static::createClient();
        $client->request('POST', '/lease/' . $this->leaseId . '/delete', [
            '_return' => '/',
        ]);

        $this->assertResponseRedirects('/');
    }

    public function testDeleteRemovesEntry(): void
    {
        $client = static::createClient();
        $client->request('POST', '/lease/' . $this->leaseId . '/delete', [
            '_return' => '/',
        ]);

        $em = static::getContainer()->get('doctrine')->getManager();
        $em->clear();
        $this->assertNull($em->find(ClientDevice::class, $this->leaseId));
    }

    public function testDeleteShowsFlashMessage(): void
    {
        $client = static::createClient();
        $client->request('POST', '/lease/' . $this->leaseId . '/delete', [
            '_return' => '/',
        ]);
        $client->followRedirect();

        $this->assertStringContainsString('Entry deleted successfully', $client->getResponse()->getContent());
    }

    public function testDeletedEntryGoneFromOverview(): void
    {
        $client = static::createClient();
        $client->request('POST', '/lease/' . $this->leaseId . '/delete', [
            '_return' => '/',
        ]);
        $client->followRedirect();

        $client->request('GET', '/');
        $this->assertStringNotContainsString('testhost', $client->getResponse()->getContent());
    }
}
