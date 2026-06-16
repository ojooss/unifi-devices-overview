<?php

declare(strict_types=1);

namespace App\Service;

class TopologyParser
{
    /**
     * Parses a topology.json file and returns a MAC → UniFi alias map.
     *
     * @return array<string, string> lowercase MAC → alias name
     */
    public function parse(string $json): array
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return [];
        }

        $aliases = [];
        foreach ($data as $site) {
            if (!is_array($site) || !isset($site['vertices']) || !is_array($site['vertices'])) {
                continue;
            }
            foreach ($site['vertices'] as $vertex) {
                if (!isset($vertex['mac'], $vertex['name'])) {
                    continue;
                }
                $mac = strtolower((string) $vertex['mac']);
                $name = trim((string) $vertex['name']);
                if ($name === '') {
                    continue;
                }
                // UniFi appends the last two MAC octets as a suffix (e.g. " 2c:f5") for disambiguation.
                $macSuffix = ' ' . substr($mac, -5);
                if (str_ends_with(strtolower($name), $macSuffix)) {
                    $name = trim(substr($name, 0, -strlen($macSuffix)));
                }
                if ($name === '') {
                    continue;
                }
                $aliases[$mac] = $name;
            }
        }

        return $aliases;
    }
}
