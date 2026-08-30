<?php

namespace App\Tests\Controller;

use App\Entity\ClientDevice;
use Doctrine\ORM\EntityManagerInterface;

class OverviewSortTest extends AbstractControllerTest
{
    protected function loadFixtures(EntityManagerInterface $em): void
    {
        $alpha = new ClientDevice('aa:aa:aa:aa:aa:aa', new \DateTimeImmutable('2024-01-01 00:00:00'));
        $alpha->update(
            null,
            '10.0.0.1',
            'alpha-host',
            'dynamic',
            null,
            new \DateTimeImmutable('2024-01-01 00:00:00'),
            new \DateTimeImmutable('2024-01-01 00:00:00')
        );
        $em->persist($alpha);

        $zeta = new ClientDevice('zz:zz:zz:zz:zz:zz', new \DateTimeImmutable('2024-01-01 00:00:00'));
        $zeta->update(
            null,
            '10.0.0.2',
            'zeta-host',
            'dynamic',
            null,
            new \DateTimeImmutable('2024-01-01 00:00:00'),
            new \DateTimeImmutable('2024-01-01 00:00:00')
        );
        $em->persist($zeta);
    }

    public function testSortByNameAscendingOrdersHostnamesAlphabetically(): void
    {
        $client = static::createClient();
        $client->request('GET', '/', ['sort' => 'name', 'dir' => 'asc']);

        $this->assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent();
        $this->assertLessThan(
            strpos($content, 'zeta-host'),
            strpos($content, 'alpha-host')
        );
    }

    public function testSortByNameDescendingReversesOrder(): void
    {
        $client = static::createClient();
        $client->request('GET', '/', ['sort' => 'name', 'dir' => 'desc']);

        $this->assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent();
        $this->assertLessThan(
            strpos($content, 'alpha-host'),
            strpos($content, 'zeta-host')
        );
    }

    public function testUnknownSortColumnFallsBackToDefaultOrder(): void
    {
        $client = static::createClient();
        $client->request('GET', '/', ['sort' => 'not-a-real-column']);

        $this->assertResponseIsSuccessful();
    }
}
