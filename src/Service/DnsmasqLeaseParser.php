<?php

namespace App\Service;

class DnsmasqLeaseParser
{
    /**
     * Parst den Inhalt einer dnsmasq.lease-Datei.
     *
     * Format pro Zeile: <Unix-Timestamp> <MAC> <IP> <Hostname> <Client-ID>
     *
     * @return array<int, array{mac: string, ip: string, hostname: ?string, leaseExpiresAt: ?\DateTimeImmutable}>
     */
    public function parse(string $content): array
    {
        $leases = [];

        foreach (explode("\n", trim($content)) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = explode(' ', $line, 5);
            if (count($parts) < 4) {
                continue;
            }

            [$timestamp, $mac, $ip, $hostname] = $parts;

            $leaseExpiresAt = \DateTimeImmutable::createFromFormat('U', $timestamp);

            $leases[] = [
                'mac' => strtolower($mac),
                'ip' => $ip,
                'hostname' => ($hostname === '*') ? null : $hostname,
                'leaseExpiresAt' => $leaseExpiresAt ?: null,
            ];
        }

        return $leases;
    }
}
