<?php

namespace App\Service;

use App\Entity\ClientDevice;
use App\Entity\Network;
use App\Repository\ClientDeviceRepository;
use App\Repository\NetworkRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class UnifiScanService
{
    public function __construct(
        private readonly UnifiApiClient $apiClient,
        private readonly EntityManagerInterface $em,
        private readonly NetworkRepository $networkRepository,
        private readonly ClientDeviceRepository $deviceRepository,
        private readonly LoggerInterface $logger,
        private readonly WebPortProbe $portProbe,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->apiClient->isConfigured();
    }

    /**
     * Queries the Dream Machine, creates new devices and updates known ones (matched by MAC).
     * Devices missing from the response are left untouched.
     *
     * @return array{created: int, updated: int}
     * @throws \RuntimeException when the Dream Machine cannot be queried
     */
    public function scan(): array
    {
        // Fetch first so that nothing is written when the controller is unreachable.
        $clients = $this->apiClient->fetchClients();
        $networks = $this->syncNetworks();

        $devices = [];
        foreach ($this->deviceRepository->findAll() as $device) {
            $devices[$device->getMacAddress()] = $device;
        }

        $now = new \DateTimeImmutable();
        $created = 0;
        $updated = 0;
        /** @var array<string, ClientDevice> $scanned */
        $scanned = [];

        foreach ($clients as $client) {
            $device = $devices[$client['mac']] ?? null;
            if ($device === null) {
                $device = new ClientDevice($client['mac'], $client['seenAt']);
                $devices[$client['mac']] = $device;
                $created++;
            } else {
                $updated++;
            }

            $device->updateFromScan(
                $client['network'] !== null ? ($networks[$client['network']] ?? null) : null,
                $client['ip'],
                $client['hostname'],
                $client['fixed'] ? 'fixed' : 'dynamic',
                $now,
                $client['seenAt'],
                $client['name'],
            );
            $this->em->persist($device);
            $scanned[$client['mac']] = $device;
        }

        $this->probeWebPorts($scanned);
        $this->em->flush();

        return ['created' => $created, 'updated' => $updated];
    }

    /**
     * @param array<string, ClientDevice> $devices
     */
    private function probeWebPorts(array $devices): void
    {
        $ips = array_map(static fn (ClientDevice $d): string => $d->getIpAddress(), $devices);
        $open = $this->portProbe->probe(array_values($ips));

        foreach ($devices as $device) {
            $device->setWebPorts($open[$device->getIpAddress()] ?? []);
        }
    }

    /**
     * Creates or updates the networks reported by the controller.
     * A failure here is not fatal: devices are then matched against the networks already stored.
     *
     * @return array<string, Network> keyed by network name
     */
    private function syncNetworks(): array
    {
        $networks = [];
        foreach ($this->networkRepository->findAll() as $network) {
            $networks[$network->getName()] = $network;
        }

        try {
            $reported = $this->apiClient->fetchNetworks();
        } catch (\RuntimeException $e) {
            $this->logger->warning('Could not read networks from UniFi: {message}', ['message' => $e->getMessage()]);

            return $networks;
        }

        foreach ($reported as $item) {
            $network = $networks[$item['name']] ?? new Network($item['name'], $item['subnet']);
            $network->setSubnet($item['subnet']);
            $network->setDhcpRange($item['rangeStart'], $item['rangeEnd']);
            $this->em->persist($network);
            $networks[$item['name']] = $network;
        }

        return $networks;
    }
}
