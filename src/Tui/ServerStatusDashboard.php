<?php

/**
 * @package     Localzet Console
 * @link        https://github.com/localzet/Console
 *
 * @author      Ivan Zorin <creator@localzet.com>
 * @copyright   Copyright © 2016-2026 Localzet Group. All rights reserved.
 * @license     https://www.gnu.org/licenses/agpl-3.0 GNU Affero General Public License v3.0
 *
 *              This program is free software: you can redistribute it and/or modify
 *              it under the terms of the GNU Affero General Public License as published
 *              by the Free Software Foundation, either version 3 of the License, or
 *              (at your option) any later version.
 *
 *              This program is distributed in the hope that it will be useful,
 *              but WITHOUT ANY WARRANTY; without even the implied warranty of
 *              MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 *              GNU Affero General Public License for more details.
 *
 *              You should have received a copy of the GNU Affero General Public License
 *              along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace localzet\Console\Tui;

/** Renders the JSON status snapshot produced by Localzet Server's CLI. */
final class ServerStatusDashboard
{
    private Dashboard $dashboard;

    public function __construct()
    {
        $this->dashboard = new Dashboard();
    }

    /** @return list<string> */
    public function render(array $status, int $width, int $height): array
    {
        $master = is_array($status['master'] ?? null) ? $status['master'] : [];
        $workers = is_array($status['workers'] ?? null) ? $status['workers'] : [];
        $running = (bool)($status['running'] ?? ($master['running'] ?? false));

        $connections = 0;
        $requests = 0;
        $memory = 0;
        foreach ($workers as $worker) {
            if (!is_array($worker)) {
                continue;
            }
            $connections += max(0, (int)($worker['connections'] ?? 0));
            $requests += max(0, (int)($worker['processed_messages'] ?? 0));
            $memory += max(0, (int)($worker['memory'] ?? 0));
        }

        $servers = is_array($master['servers'] ?? null) ? $master['servers'] : [];
        $configuredWorkers = 0;
        $connectionCapacity = 0;
        $hasUnlimitedConnections = false;
        $sections = [];

        foreach ($servers as $index => $server) {
            if (!is_array($server)) {
                continue;
            }

            $name = trim((string)($server['name'] ?? ''));
            $name = $name !== '' ? $name : 'Server ' . ($index + 1);
            if (isset($server['id'])) {
                $name .= ' #' . (string)$server['id'];
            }

            $listen = trim((string)($server['listen'] ?? ''));
            $protocol = trim((string)($server['protocol'] ?? $server['transport'] ?? 'TCP'));
            $serverWorkers = is_array($server['workers'] ?? null) ? $server['workers'] : [];
            $configured = max(0, (int)($server['configured_workers'] ?? count($serverWorkers)));
            $configuredWorkers += $configured;

            $limits = is_array($server['limits'] ?? null) ? $server['limits'] : [];
            $maxConnections = max(0, (int)($limits['max_connections'] ?? 0));
            if ($maxConnections === 0) {
                $hasUnlimitedConnections = true;
            } else {
                $connectionCapacity += $maxConnections * $configured;
            }

            $sections[$name] = [
                ($listen !== '' ? $listen : 'Listener')
                . ' · ' . $protocol
                . ' · workers ' . count($serverWorkers) . '/' . $configured,
            ];
        }

        if ($sections === []) {
            $sections['Listeners'] = [$running ? 'No listener details are available.' : 'Server is stopped.'];
        }

        $connectionMetric = ['value' => (string)$connections];
        if (!$hasUnlimitedConnections && $connectionCapacity > 0) {
            $connectionMetric['percent'] = min(100, $connections / $connectionCapacity * 100);
            $connectionMetric['detail'] = 'of ' . $connectionCapacity;
        }

        $error = $status['error'] ?? null;
        if (is_string($error) && $error !== '') {
            $sections = ['Status' => [$error]];
        }

        $state = is_string($error) && $error !== ''
            ? 'unavailable'
            : ($running ? (string)($master['state'] ?? 'running') : 'stopped');

        return $this->dashboard->render(
            'Localzet Server',
            [
                'State' => ['value' => $state],
                'Uptime' => ['value' => self::formatDuration((float)($master['uptime'] ?? 0))],
                'Workers' => ['value' => count($workers) . ' / ' . $configuredWorkers],
                'Connections' => $connectionMetric,
                'Requests' => ['value' => (string)$requests],
                'Memory' => ['value' => self::formatBytes($memory)],
            ],
            $sections,
            'Q — quit · R — refresh · refreshes every second',
            $width,
            $height
        );
    }

    private static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1024 ** 2) {
            return number_format($bytes / 1024, 1) . ' KiB';
        }
        if ($bytes < 1024 ** 3) {
            return number_format($bytes / 1024 ** 2, 1) . ' MiB';
        }
        return number_format($bytes / 1024 ** 3, 1) . ' GiB';
    }

    private static function formatDuration(float $seconds): string
    {
        $seconds = max(0, (int)$seconds);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        if ($hours > 0) {
            return $hours . 'h ' . $minutes . 'm';
        }
        if ($minutes > 0) {
            return $minutes . 'm ' . $remainingSeconds . 's';
        }
        return $remainingSeconds . 's';
    }
}
