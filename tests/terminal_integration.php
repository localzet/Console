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

if (PHP_OS_FAMILY !== 'Linux' || !function_exists('proc_open')) {
    echo "SKIP: pseudo-terminal integration requires Linux and proc_open.\n";
    exit(0);
}

$scriptBinary = null;
foreach (explode(PATH_SEPARATOR, (string)getenv('PATH')) as $directory) {
    $candidate = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'script';
    if (is_file($candidate) && is_executable($candidate)) {
        $scriptBinary = $candidate;
        break;
    }
}
if ($scriptBinary === null) {
    echo "SKIP: pseudo-terminal integration requires util-linux script.\n";
    exit(0);
}

$fixture = __DIR__ . '/fixtures/live_status_cli.php';
$scriptCommand = 'stty rows 24 cols 80; '
    . escapeshellarg(PHP_BINARY)
    . ' '
    . escapeshellarg($fixture)
    . ' status --live';
$process = proc_open(
    [$scriptBinary, '-qec', $scriptCommand, '/dev/null'],
    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $pipes
);
if (!is_resource($process)) {
    throw new RuntimeException('Unable to start the pseudo-terminal fixture.');
}

fwrite($pipes[0], 'q');
fclose($pipes[0]);
stream_set_blocking($pipes[1], false);
stream_set_blocking($pipes[2], false);
$stdout = '';
$stderr = '';
$deadline = microtime(true) + 5.0;
$exitCode = null;

try {
    do {
        $stdout .= (string)stream_get_contents($pipes[1]);
        $stderr .= (string)stream_get_contents($pipes[2]);
        if (strlen($stdout) > 1_048_576 || strlen($stderr) > 1_048_576) {
            throw new RuntimeException('Pseudo-terminal output exceeded the size limit.');
        }

        $status = proc_get_status($process);
        if (!$status['running']) {
            $exitCode = $status['exitcode'];
            break;
        }
        if (microtime(true) >= $deadline) {
            throw new RuntimeException('Pseudo-terminal fixture timed out.');
        }
        usleep(10_000);
    } while (true);

    $stdout .= (string)stream_get_contents($pipes[1]);
    $stderr .= (string)stream_get_contents($pipes[2]);
} finally {
    $status = proc_get_status($process);
    if ($status['running']) {
        proc_terminate($process, 9);
    }
    foreach ([1, 2] as $pipe) {
        if (is_resource($pipes[$pipe])) {
            fclose($pipes[$pipe]);
        }
    }
    proc_close($process);
}

if ($exitCode !== 0 && $exitCode !== -1) {
    throw new RuntimeException('Pseudo-terminal fixture failed: ' . trim($stderr . "\n" . $stdout));
}
if (!str_contains($stdout, 'Localzet Server') || !str_contains($stdout, 'integration')) {
    throw new RuntimeException('Live status dashboard did not render its title and listener data.');
}
if (!str_contains($stdout, "\033[?25h") || !str_contains($stdout, "\033[?1049l")) {
    throw new RuntimeException('Live status did not restore terminal visibility and screen state.');
}

echo "INTERACTIVE_STATUS_DASHBOARD=ok\n";
