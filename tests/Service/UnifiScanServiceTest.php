<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\ClientDevice;
use App\Entity\Network;
use App\Repository\ClientDeviceRepository;
use App\Repository\NetworkRepository;
use App\Service\UnifiApiClient;
use App\Service\UnifiScanService;
use App\Service\WebPortProbe;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class UnifiScanServiceTest extends TestCase
{
    /**
     * @param list<array<string, mixed>> $clients
     * @param list<array<string, mixed>> $networks
     * @param list<ClientDevice> $knownDevices
     * @param list<Network> $knownNetworks
     * @param array<string, list<int>> $openPorts
     */
    private function makeService(
        array $clients,
        array $networks = [],
        array $knownDevices = [],
        array $knownNetworks = [],
        ?EntityManagerInterface $em = null,
        array $openPorts = [],
    ): UnifiScanService {
        $probe = $this->createStub(WebPortProbe::class);
        $probe->method('probe')->willReturn($openPorts);

        $api = $this->createStub(UnifiApiClient::class);
        $api->method('isConfigured')->willReturn(true);
        $api->method('fetchClients')->willReturn($clients);
        $api->method('fetchNetworks')->willReturn($networks);

        $networkRepo = $this->createStub(NetworkRepository::class);
        $networkRepo->method('findAll')->willReturn($knownNetworks);
        $deviceRepo = $this->createStub(ClientDeviceRepository::class);
        $deviceRepo->method('findAll')->willReturn($knownDevices);

        return new UnifiScanService(
            $api,
            $em ?? $this->createStub(EntityManagerInterface::class),
            $networkRepo,
            $deviceRepo,
            new NullLogger(),
            $probe,
        );
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function client(string $mac, array $overrides = []): array
    {
        return $overrides + [
            'mac' => $mac,
            'ip' => '192.168.3.10',
            'hostname' => 'host',
            'name' => 'Alias',
            'fixed' => false,
            'seenAt' => new \DateTimeImmutable('2026-05-01 12:00:00'),
            'network' => null,
        ];
    }

    public function testCreatesNewAndUpdatesKnownDevices(): void
    {
        $known = new ClientDevice('aa:aa:aa:aa:aa:01', new \DateTimeImmutable('2026-01-01'));

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->exactly(2))->method('persist');
        $em->expects($this->once())->method('flush');

        $result = $this->makeService(
            [$this->client('aa:aa:aa:aa:aa:01'), $this->client('aa:aa:aa:aa:aa:02', ['ip' => '192.168.3.11'])],
            knownDevices: [$known],
            em: $em,
        )->scan();

        $this->assertSame(['created' => 1, 'updated' => 1], $result);
        $this->assertSame('192.168.3.10', $known->getIpAddress());
        $this->assertSame('Alias', $known->getUnifiAlias());
    }

    public function testDuplicateMacInResponseIsCountedOnce(): void
    {
        $result = $this->makeService([
            $this->client('aa:aa:aa:aa:aa:01'),
            $this->client('aa:aa:aa:aa:aa:01'),
        ])->scan();

        $this->assertSame(['created' => 1, 'updated' => 1], $result);
    }

    public function testKeepsUserEditsAndDataTheApiDoesNotProvide(): void
    {
        $device = new ClientDevice('aa:aa:aa:aa:aa:01', new \DateTimeImmutable('2026-01-01'));
        $device->setCustomName('My name');
        $device->setRemark('My remark');
        $expires = new \DateTimeImmutable('2026-12-31');
        $device->update(
            null,
            '192.168.3.5',
            'dnsmasq-host',
            'fixed',
            $expires,
            new \DateTimeImmutable(),
            new \DateTimeImmutable('2026-01-01'),
        );

        $this->makeService(
            [$this->client('aa:aa:aa:aa:aa:01', ['hostname' => null, 'ip' => '', 'name' => null])],
            knownDevices: [$device],
        )->scan();

        $this->assertSame('My name', $device->getCustomName());
        $this->assertSame('My remark', $device->getRemark());
        $this->assertSame($expires, $device->getLeaseExpiresAt());
        $this->assertSame('dnsmasq-host', $device->getHostname());
        $this->assertSame('192.168.3.5', $device->getIpAddress());
        $this->assertSame('dynamic', $device->getIpType());
    }

    public function testOlderScanDoesNotMoveSeenAtBackwards(): void
    {
        $newer = new \DateTimeImmutable('2026-09-01');
        $device = new ClientDevice('aa:aa:aa:aa:aa:01', $newer);
        $device->update(null, '192.168.3.99', 'h', 'dynamic', null, new \DateTimeImmutable(), $newer);

        $this->makeService([$this->client('aa:aa:aa:aa:aa:01')], knownDevices: [$device])->scan();

        $this->assertSame($newer, $device->getSeenAt());
        $this->assertSame('192.168.3.99', $device->getIpAddress());
        $this->assertSame('Alias', $device->getUnifiAlias());
    }

    public function testAssignsNetworkByNameAndCreatesReportedNetworks(): void
    {
        $existing = new Network('DMZ', '0.0.0.0/0');

        $em = $this->createStub(EntityManagerInterface::class);
        $persisted = [];
        $em->method('persist')->willReturnCallback(function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $this->makeService(
            [
                $this->client('aa:aa:aa:aa:aa:01', ['network' => 'DMZ']),
                $this->client('aa:aa:aa:aa:aa:02', ['network' => 'Office']),
            ],
            networks: [
                [
                    'name' => 'DMZ',
                    'subnet' => '192.168.3.0/24',
                    'rangeStart' => '192.168.3.6',
                    'rangeEnd' => '192.168.3.254',
                ],
                ['name' => 'Office', 'subnet' => '192.168.4.0/24', 'rangeStart' => null, 'rangeEnd' => null],
            ],
            knownNetworks: [$existing],
            em: $em,
        )->scan();

        $this->assertSame('192.168.3.0/24', $existing->getSubnet());
        $this->assertSame('192.168.3.6', $existing->getDhcpRangeStart());

        $devices = array_values(array_filter($persisted, fn (object $e): bool => $e instanceof ClientDevice));
        $this->assertCount(2, $devices);
        $this->assertSame($existing, $devices[0]->getNetwork());
        $this->assertSame('Office', $devices[1]->getNetwork()?->getName());
        $this->assertCount(4, $persisted); // 2 networks + 2 devices
    }

    public function testNetworkFailureDoesNotAbortTheScan(): void
    {
        $api = $this->createStub(UnifiApiClient::class);
        $api->method('fetchClients')->willReturn([$this->client('aa:aa:aa:aa:aa:01')]);
        $api->method('fetchNetworks')->willThrowException(new \RuntimeException('boom'));

        $networkRepo = $this->createStub(NetworkRepository::class);
        $networkRepo->method('findAll')->willReturn([]);
        $deviceRepo = $this->createStub(ClientDeviceRepository::class);
        $deviceRepo->method('findAll')->willReturn([]);

        $service = new UnifiScanService(
            $api,
            $this->createStub(EntityManagerInterface::class),
            $networkRepo,
            $deviceRepo,
            new NullLogger(),
            $this->createStub(WebPortProbe::class),
        );

        $this->assertSame(['created' => 1, 'updated' => 0], $service->scan());
    }

    public function testClientFailureWritesNothing(): void
    {
        $api = $this->createStub(UnifiApiClient::class);
        $api->method('fetchClients')->willThrowException(new \RuntimeException('unreachable'));

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush');

        $service = new UnifiScanService(
            $api,
            $em,
            $this->createStub(NetworkRepository::class),
            $this->createStub(ClientDeviceRepository::class),
            new NullLogger(),
            $this->createStub(WebPortProbe::class),
        );

        $this->expectException(\RuntimeException::class);
        $service->scan();
    }

    public function testStoresOpenWebPortsAndBuildsLink(): void
    {
        $known = new ClientDevice('aa:aa:aa:aa:aa:01', new \DateTimeImmutable('2026-01-01'));
        $other = new ClientDevice('aa:aa:aa:aa:aa:02', new \DateTimeImmutable('2026-01-01'));

        $this->makeService(
            [$this->client('aa:aa:aa:aa:aa:01'), $this->client('aa:aa:aa:aa:aa:02', ['ip' => '192.168.3.11'])],
            knownDevices: [$known, $other],
            openPorts: ['192.168.3.10' => [80, 443]],
        )->scan();

        $this->assertSame('https://192.168.3.10', $known->getWebUrl());
        $this->assertNull($other->getWebUrl());
    }

    public function testWebUrlFallsBackToHttpPorts(): void
    {
        $device = new ClientDevice('aa:aa:aa:aa:aa:01', new \DateTimeImmutable('2026-01-01'));
        $device->update(null, '192.168.3.5', null, 'dynamic', null, new \DateTimeImmutable(), new \DateTimeImmutable());

        $device->setWebPorts([8080]);
        $this->assertSame('http://192.168.3.5:8080', $device->getWebUrl());

        $device->setWebPorts([8080, 80]);
        $this->assertSame('http://192.168.3.5', $device->getWebUrl());

        $device->setWebPorts([]);
        $this->assertNull($device->getWebUrl());
    }
}
