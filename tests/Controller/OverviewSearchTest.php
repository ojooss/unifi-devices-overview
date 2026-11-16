<?php

namespace App\Tests\Controller;

use App\Entity\ClientDevice;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;

class OverviewSearchTest extends AbstractControllerTest
{
    protected function loadFixtures(EntityManagerInterface $em): void
    {
        $now = new \DateTimeImmutable('2024-01-01 00:00:00');

        $first = new ClientDevice('aa:bb:cc:00:00:01', $now);
        $first->update(null, '10.1.2.3', 'host-one', 'dynamic', null, $now, $now, 'alias-one');
        $first->setCustomName('custom-one');
        $first->setRemark('remark-one');
        $em->persist($first);

        $second = new ClientDevice('dd:ee:ff:00:00:02', $now);
        $second->update(null, '10.9.9.9', 'host-two', 'dynamic', null, $now, $now, 'alias-two');
        $second->setCustomName('custom-two');
        $second->setRemark('remark-two');
        $em->persist($second);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function searchableColumns(): iterable
    {
        yield 'name' => ['custom-one'];
        yield 'alias' => ['alias-one'];
        yield 'hostname' => ['host-one'];
        yield 'remark' => ['remark-one'];
        yield 'mac' => ['aa:bb:cc'];
        yield 'ip' => ['10.1.2'];
    }

    #[DataProvider('searchableColumns')]
    public function testSearchFindsDeviceByColumn(string $term): void
    {
        $client = static::createClient();
        $client->request('GET', '/', ['search' => $term]);

        $this->assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent();
        $this->assertStringContainsString('host-one', $content);
        $this->assertStringNotContainsString('host-two', $content);
    }
}
