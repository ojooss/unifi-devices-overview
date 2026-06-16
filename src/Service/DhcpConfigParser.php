<?php

namespace App\Service;

class DhcpConfigParser
{
    /**
     * Parst eine dnsmasq DHCP-Server-Konfigurationsdatei.
     *
     * @param string $identifier Dateiname ohne .conf, z.B. "net_DMZ_br3_192-168-3-0-24"
     * @return array{name: string, subnet: string, fixedMacs: array<string,string>,
     *               rangeStart: ?string, rangeEnd: ?string}
     */
    public function parse(string $content, string $identifier): array
    {
        // Netzwerkname und Subnet aus Identifier extrahieren
        // Format: net_<NAME>_br<N>_<A>-<B>-<C>-<D>-<PREFIX>
        if (preg_match('/net_([^_]+)_br\d+_(\d+)-(\d+)-(\d+)-(\d+)-(\d+)/', $identifier, $m)) {
            $name = $m[1];
            $subnet = "{$m[2]}.{$m[3]}.{$m[4]}.{$m[5]}/{$m[6]}";
        } else {
            $name = $identifier;
            $subnet = '0.0.0.0/0';
        }

        // Feste IP-Zuweisungen: dhcp-host=set:...,id:*,<MAC>,<IP>
        preg_match_all(
            '/dhcp-host=[^,]+,[^,]+,([0-9a-fA-F:]{17}),(\d+\.\d+\.\d+\.\d+)/',
            $content,
            $hosts
        );
        $fixedMacs = [];
        if (isset($hosts[1]) && $hosts[1] !== []) {
            $fixedMacs = array_combine(
                array_map(strtolower(...), $hosts[1]),
                $hosts[2]
            );
        }

        // Dynamischer Bereich: dhcp-range=set:...,<start>,<end>,...
        preg_match(
            '/dhcp-range=[^,]+,(\d+\.\d+\.\d+\.\d+),(\d+\.\d+\.\d+\.\d+)/',
            $content,
            $range
        );

        return [
            'name' => $name,
            'subnet' => $subnet,
            'fixedMacs' => $fixedMacs,
            'rangeStart' => $range[1] ?? null,
            'rangeEnd' => $range[2] ?? null,
        ];
    }
}
