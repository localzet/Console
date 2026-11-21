<?php

declare(strict_types=1);

/**
 * @package     Localzet Console library
 * @link        https://github.com/localzet/Console
 *
 * @author      Ivan Zorin <creator@localzet.com>
 * @copyright   Copyright © 2016-2026 Localzet Group. All rights reserved.
 * @license     GNU Affero General Public License, version 3
 *
 *              This program is free software: you can redistribute it and/or modify
 *              it under the terms of the GNU Affero General Public License as
 *              published by the Free Software Foundation, either version 3 of the
 *              License, or (at your option) any later version.
 *
 *              This program is distributed in the hope that it will be useful,
 *              but WITHOUT ANY WARRANTY; without even the implied warranty of
 *              MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 *              GNU Affero General Public License for more details.
 *
 *              You should have received a copy of the GNU Affero General Public License
 *              along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 *              For any questions, please contact <support@localzet.com>
 */

namespace localzet\Console\Commands;

use localzet\Console\Tui\ServerStatusDashboard;
use localzet\Console\Tui\Terminal;
use localzet\Server;
use support\App;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * @author Ivan Zorin <creator@localzet.com>
 */
class StatusCommand extends Command
{
    protected static string $defaultName = 'status';
    protected static string $defaultDescription = 'Статус сервера. Используй -d, чтобы показать статус в реальном времени.';

    protected function configure(): void
    {
        $this->addOption('live', 'd', InputOption::VALUE_NONE, 'Статус в реальном времени');
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Вывод статуса в формате JSON');

        if (!class_exists('\\support\\App') && !class_exists('\\localzet\\Server')) {
            $this->setHidden();
        }
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws Throwable
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('live') && class_exists(Server::class) && !defined('GLOBAL_START')) {
            return $this->runLiveStatus($output);
        }

        if (class_exists('\\support\\App')) {
            App::run();
            return self::SUCCESS;
        }

        if (class_exists('\\localzet\\Server') && !defined('GLOBAL_START')) {
            Server::runAll();
            return self::SUCCESS;
        }

        return self::FAILURE;
    }

    private function runLiveStatus(OutputInterface $output): int
    {
        if (!function_exists('proc_open')) {
            $output->writeln('<error>Live status requires the proc_open function.</error>');
            return self::FAILURE;
        }

        $terminal = new Terminal();
        $dashboard = new ServerStatusDashboard();

        try {
            $terminal->open();
            $status = [];
            $width = 80;
            $height = 24;
            $refreshAt = 0.0;
            $resizeAt = 0.0;

            while (true) {
                $now = microtime(true);
                if ($now >= $resizeAt) {
                    [$width, $height] = $terminal->resize();
                    $resizeAt = $now + 1.0;
                }

                if ($now >= $refreshAt) {
                    try {
                        $status = $this->readStatusSnapshot();
                    } catch (Throwable) {
                        $status = ['error' => 'Status snapshot is unavailable.'];
                    }
                    $refreshAt = microtime(true) + 1.0;
                }

                $terminal->draw($dashboard->render($status, $width, $height));
                $input = $terminal->readInput();
                if (strpbrk($input, 'qQ') !== false) {
                    break;
                }
                if (strpbrk($input, 'rR') !== false) {
                    $refreshAt = 0.0;
                }
                usleep(50_000);
            }

            return self::SUCCESS;
        } catch (Throwable) {
            $output->writeln('<error>Live status requires an interactive Unix terminal and the current CLI entry point.</error>');
            return self::FAILURE;
        } finally {
            $terminal->close();
        }
    }

    private function readStatusSnapshot(): array
    {
        $arguments = $_SERVER['argv'] ?? [];
        $entrypoint = $arguments[0] ?? null;
        if (!is_string($entrypoint) || $entrypoint === '' || !is_file($entrypoint)) {
            throw new \RuntimeException('Cannot locate the current CLI entry point.');
        }

        $entrypoint = realpath($entrypoint) ?: $entrypoint;
        $process = proc_open(
            [PHP_BINARY, $entrypoint, 'status', '--json'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname($entrypoint)
        );
        if (!is_resource($process)) {
            throw new \RuntimeException('Unable to start the status command.');
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $statusOutput = '';
        $exitCode = null;
        $deadline = microtime(true) + 2.0;

        try {
            do {
                $chunk = stream_get_contents($pipes[1]);
                if (is_string($chunk)) {
                    $statusOutput .= $chunk;
                }
                stream_get_contents($pipes[2]);

                if (strlen($statusOutput) > 1_048_576) {
                    throw new \RuntimeException('Status output exceeded the size limit.');
                }

                $processStatus = proc_get_status($process);
                if (!$processStatus['running']) {
                    $exitCode = $processStatus['exitcode'];
                    break;
                }
                if (microtime(true) >= $deadline) {
                    throw new \RuntimeException('Status command timed out.');
                }
                usleep(10_000);
            } while (true);

            $remainingOutput = stream_get_contents($pipes[1]);
            if (is_string($remainingOutput)) {
                $statusOutput .= $remainingOutput;
            }
            if (strlen($statusOutput) > 1_048_576 || ($exitCode !== 0 && $exitCode !== -1)) {
                throw new \RuntimeException('Status command failed.');
            }

            $status = json_decode(trim($statusOutput), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($status)) {
                throw new \RuntimeException('Status response is invalid.');
            }

            return $status;
        } finally {
            $processStatus = proc_get_status($process);
            if ($processStatus['running']) {
                proc_terminate($process);
                usleep(100_000);
                if (proc_get_status($process)['running']) {
                    proc_terminate($process, 9);
                }
            }

            foreach ([1, 2] as $pipe) {
                if (is_resource($pipes[$pipe])) {
                    fclose($pipes[$pipe]);
                }
            }
            proc_close($process);
        }
    }
}
