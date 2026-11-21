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

namespace localzet {
    final class Server
    {
        public static function runAll(): void
        {
        }
    }
}

namespace {
    if (($argv[1] ?? null) === 'status' && in_array('--json', $argv, true)) {
        echo json_encode([
            'master' => [
                'running' => true,
                'state' => 'running',
                'uptime' => 12,
                'servers' => [[
                    'id' => 1,
                    'name' => 'integration',
                    'listen' => 'tcp://127.0.0.1:19000',
                    'transport' => 'tcp',
                    'configured_workers' => 1,
                    'limits' => ['max_connections' => 50],
                    'workers' => [['pid' => 123]],
                ]],
            ],
            'workers' => [[
                'connections' => 2,
                'processed_messages' => 5,
                'memory' => 1024 * 1024,
            ]],
        ], JSON_THROW_ON_ERROR);
        exit(0);
    }

    require dirname(__DIR__, 2) . '/vendor/autoload.php';

    $console = new \localzet\Console([], false);
    $console->addCommand(new \localzet\Console\Commands\StatusCommand());
    exit($console->run());
}
