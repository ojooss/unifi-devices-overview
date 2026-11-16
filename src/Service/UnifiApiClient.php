<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Reads clients and networks from a UniFi Dream Machine (UniFi OS) with an API key.
 *
 * Uses the classic controller endpoints below /proxy/network/api/s/<site>/.
 * Throws \RuntimeException (never including the API key) when a call fails.
 */
class UnifiApiClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $site = 'default',
        private readonly bool $verifyTls = true,
    ) {
    }

    public function isConfigured(): bool
    {
        return trim($this->baseUrl) !== '' && trim($this->apiKey) !== '';
    }

    /**
     * Returns all clients the controller knows, including those currently offline.
     *
     * @return list<array{
     *     mac: string, ip: string, hostname: ?string, name: ?string,
     *     fixed: bool, seenAt: \DateTimeImmutable, network: ?string
     * }>
     */
    public function fetchClients(): array
    {
        $clients = [];
        foreach ($this->get('rest/user') as $row) {
            $mac = is_string($row['mac'] ?? null) ? strtolower(trim($row['mac'])) : '';
            if ($mac === '') {
                continue;
            }

            $clients[] = [
                'mac' => $mac,
                'ip' => $this->ipv4($row['last_ip'] ?? $row['ip'] ?? null),
                'hostname' => $this->text($row['hostname'] ?? null),
                'name' => $this->text($row['name'] ?? null),
                'fixed' => ($row['use_fixedip'] ?? false) === true,
                'seenAt' => $this->timestamp($row['last_seen'] ?? $row['first_seen'] ?? null),
                'network' => $this->text($row['last_connection_network_name'] ?? null),
            ];
        }

        return $clients;
    }

    /**
     * Returns the configured networks that have an IPv4 subnet.
     *
     * @return list<array{name: string, subnet: string, rangeStart: ?string, rangeEnd: ?string}>
     */
    public function fetchNetworks(): array
    {
        $networks = [];
        foreach ($this->get('rest/networkconf') as $row) {
            $name = $this->text($row['name'] ?? null);
            $subnet = $this->subnet($row['ip_subnet'] ?? null);
            if ($name === null || $subnet === null) {
                continue;
            }

            $dhcpEnabled = ($row['dhcpd_enabled'] ?? false) === true;
            $networks[] = [
                'name' => $name,
                'subnet' => $subnet,
                'rangeStart' => $dhcpEnabled ? $this->text($row['dhcpd_start'] ?? null) : null,
                'rangeEnd' => $dhcpEnabled ? $this->text($row['dhcpd_stop'] ?? null) : null,
            ];
        }

        return $networks;
    }

    /**
     * Converts "192.168.3.1/24" (gateway address) to the network address "192.168.3.0/24".
     */
    public function subnet(mixed $value): ?string
    {
        if (!is_string($value) || substr_count($value, '/') !== 1) {
            return null;
        }

        [$address, $prefix] = explode('/', $value);
        $long = ip2long($address);
        if ($long === false || !ctype_digit($prefix) || (int) $prefix > 32) {
            return null;
        }

        $prefix = (int) $prefix;
        $mask = $prefix === 0 ? 0 : (~0 << (32 - $prefix)) & 0xFFFFFFFF;

        return long2ip($long & $mask) . '/' . $prefix;
    }

    /** @return list<array<string, mixed>> */
    private function get(string $path): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('UniFi API is not configured (UNIFI_BASE_URL and UNIFI_API_KEY are required).');
        }

        $url = sprintf(
            '%s/proxy/network/api/s/%s/%s',
            rtrim($this->baseUrl, '/'),
            rawurlencode($this->site),
            $path,
        );

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => ['X-API-KEY' => $this->apiKey, 'Accept' => 'application/json'],
                'verify_peer' => $this->verifyTls,
                'verify_host' => $this->verifyTls,
                'timeout' => 15,
            ]);

            $status = $response->getStatusCode();
            if ($status === 401 || $status === 403) {
                throw new \RuntimeException(sprintf('UniFi API rejected the API key (HTTP %d).', $status));
            }
            if ($status !== 200) {
                throw new \RuntimeException(sprintf('UniFi API returned HTTP %d for %s.', $status, $path));
            }

            $payload = $response->toArray(false);
        } catch (ExceptionInterface $e) {
            throw new \RuntimeException('UniFi API request failed: ' . $e->getMessage(), 0, $e);
        }

        $data = $payload['data'] ?? null;
        if (!is_array($data)) {
            throw new \RuntimeException(sprintf('Unexpected UniFi API response for %s.', $path));
        }

        return array_values(array_filter($data, is_array(...)));
    }

    private function ipv4(mixed $value): string
    {
        return is_string($value) && filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
            ? $value
            : '';
    }

    private function text(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function timestamp(mixed $value): \DateTimeImmutable
    {
        if (is_int($value) && $value > 0) {
            $date = \DateTimeImmutable::createFromFormat('U', (string) $value);
            if ($date !== false) {
                return $date;
            }
        }

        return new \DateTimeImmutable();
    }
}
