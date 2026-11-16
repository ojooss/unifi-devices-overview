<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Checks which web ports (80, 8080, 443) are open on a set of IPv4 addresses.
 * All connections are opened in parallel so that unreachable hosts do not add up.
 */
class WebPortProbe
{
    public const PORTS = [80, 8080, 443];

    public function __construct(private readonly float $timeout = 1.0)
    {
    }

    /**
     * @param list<string> $ips
     * @return array<string, list<int>> open ports per IP (only IPs with at least one open port)
     */
    public function probe(array $ips): array
    {
        $ips = array_values(array_unique(array_filter(
            $ips,
            static fn (string $ip): bool => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false,
        )));

        $result = [];
        foreach (array_chunk($ips, 100) as $chunk) {
            foreach ($this->probeChunk($chunk) as $ip => $ports) {
                $result[$ip] = $ports;
            }
        }

        return $result;
    }

    /**
     * @param list<string> $ips
     * @return array<string, list<int>>
     */
    private function probeChunk(array $ips): array
    {
        /** @var array<int, array{ip: string, port: int, socket: resource}> $pending */
        $pending = [];
        foreach ($ips as $ip) {
            foreach (self::PORTS as $port) {
                $socket = @stream_socket_client(
                    sprintf('tcp://%s:%d', $ip, $port),
                    $errno,
                    $error,
                    $this->timeout,
                    STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT,
                );
                if ($socket !== false) {
                    $pending[(int) $socket] = ['ip' => $ip, 'port' => $port, 'socket' => $socket];
                }
            }
        }

        $open = [];
        $deadline = microtime(true) + $this->timeout;

        while ($pending !== [] && ($remaining = $deadline - microtime(true)) > 0) {
            $write = $except = array_column($pending, 'socket');
            $read = null;
            $seconds = (int) $remaining;
            $micro = (int) (($remaining - $seconds) * 1_000_000);
            if (@stream_select($read, $write, $except, $seconds, $micro) === false) {
                break;
            }

            foreach ($write as $socket) {
                $entry = $pending[(int) $socket];
                // A refused connection also becomes writable; detect it by trying to read the peer.
                if (@stream_socket_get_name($socket, true) !== false) {
                    $open[$entry['ip']][] = $entry['port'];
                }
                fclose($socket);
                unset($pending[(int) $socket]);
            }
            foreach ($except as $socket) {
                if (isset($pending[(int) $socket])) {
                    fclose($socket);
                    unset($pending[(int) $socket]);
                }
            }
        }

        foreach ($pending as $entry) {
            fclose($entry['socket']);
        }

        foreach ($open as &$ports) {
            sort($ports);
        }

        return $open;
    }
}
