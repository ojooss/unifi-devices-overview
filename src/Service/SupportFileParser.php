<?php

namespace App\Service;

use App\Entity\ClientDevice;
use App\Entity\Network;
use App\Repository\ClientDeviceRepository;
use App\Repository\NetworkRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class SupportFileParser
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly NetworkRepository $networkRepository,
        private readonly ClientDeviceRepository $leaseRepository,
        private readonly DhcpConfigParser $dhcpConfigParser,
        private readonly DnsmasqLeaseParser $leaseParser,
    ) {
    }

    /**
     * Importiert eine UniFi-Support-Datei und gibt die Anzahl der Lease-Einträge zurück.
     *
     * @throws \RuntimeException wenn die Datei nicht geparst werden kann
     */
    public function parse(UploadedFile $file): int
    {
        $capturedAt = $this->extractTimestamp($file->getClientOriginalName());
        $now = new \DateTimeImmutable();

        // Use system tar instead of PharData: avoids GNU LongLink limitations
        // (paths > 100 chars) and prevents OOM from in-memory decompression.
        $tmpPath = sys_get_temp_dir() . '/' . bin2hex(random_bytes(8)) . '.tgz';
        $tmpDir = sys_get_temp_dir() . '/' . bin2hex(random_bytes(8));
        copy($file->getPathname(), $tmpPath);
        mkdir($tmpDir, 0700);

        $networks = [];
        $fixedMacs = [];
        $leaseContent = null;

        try {
            exec('tar -xzf ' . escapeshellarg($tmpPath) . ' -C ' . escapeshellarg($tmpDir) . ' 2>&1', $output, $code);
            if ($code !== 0) {
                throw new \RuntimeException('Failed to extract archive: ' . implode("\n", $output));
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($tmpDir, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $fileInfo) {
                if ($fileInfo->isDir()) {
                    continue;
                }

                $path = $fileInfo->getPathname();
                $filename = $fileInfo->getFilename();

                if (preg_match('/^dhcp\.dhcpServers-(.+)\.conf$/', $filename, $m)) {
                    $result = $this->dhcpConfigParser->parse(
                        file_get_contents($path),
                        $m[1]
                    );

                    $network = $this->networkRepository->findOneBy(['name' => $result['name']])
                        ?? new Network($result['name'], $result['subnet']);
                    $network->setSubnet($result['subnet']);
                    $network->setDhcpRange($result['rangeStart'], $result['rangeEnd']);
                    $this->em->persist($network);

                    $networks[$result['name']] = $network;
                    foreach ($result['fixedMacs'] as $mac => $ip) {
                        $fixedMacs[strtolower($mac)] = $ip;
                    }

                    continue;
                }

                if ($filename === 'dnsmasq.lease' && str_contains($path, 'udapi-config')) {
                    $leaseContent = file_get_contents($path);
                }
            }
        } finally {
            @unlink($tmpPath);
            $this->removeDir($tmpDir);
        }

        $this->em->flush();

        if ($leaseContent === null) {
            throw new \RuntimeException('No dnsmasq.lease file found in the support archive.');
        }

        $count = 0;
        $leases = $this->leaseParser->parse($leaseContent);

        foreach ($leases as $lease) {
            $network = $this->findNetworkForIp($lease['ip'], $networks);
            $ipType = isset($fixedMacs[$lease['mac']]) ? 'fixed' : 'dynamic';

            $clientDevice = $this->leaseRepository->findOneBy([
                'macAddress' => $lease['mac'],
                'seenAt' => $capturedAt,
            ]) ?? new ClientDevice($lease['mac'], $capturedAt);

            $clientDevice->update(
                $network,
                $lease['ip'],
                $lease['hostname'],
                $ipType,
                $lease['leaseExpiresAt'],
                $now,
            );
            $this->em->persist($clientDevice);
            $count++;
        }

        $this->em->flush();

        return $count;
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($dir);
    }

    private function extractTimestamp(string $filename): \DateTimeImmutable
    {
        // Format: support-XXXX-<milliseconds>.tgz
        if (preg_match('/support-\d+-(\d+)\.tgz$/i', $filename, $m)) {
            $seconds = (int) ((int) $m[1] / 1000);
            $dt = \DateTimeImmutable::createFromFormat('U', (string) $seconds);
            if ($dt !== false) {
                return $dt;
            }
        }

        return new \DateTimeImmutable();
    }

    /** @param array<string, Network> $networks */
    private function findNetworkForIp(string $ip, array $networks): ?Network
    {
        $ipLong = ip2long($ip);
        if ($ipLong === false) {
            return null;
        }

        foreach ($networks as $network) {
            $parts = explode('/', $network->getSubnet());
            if (count($parts) !== 2) {
                continue;
            }
            $subnetLong = ip2long($parts[0]);
            $prefix = (int) $parts[1];
            if ($subnetLong === false || $prefix < 0 || $prefix > 32) {
                continue;
            }
            $mask = $prefix === 0 ? 0 : (~0 << (32 - $prefix));
            if (($ipLong & $mask) === ($subnetLong & $mask)) {
                return $network;
            }
        }

        return null;
    }
}
