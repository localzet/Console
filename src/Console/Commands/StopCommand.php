<?php

declare(strict_types=1);

/**
 * @package     Localzet Console library
 * @link        https://github.com/localzet/Console
 *
 * @author      Ivan Zorin <creator@localzet.com>
 * @copyright   Copyright (c) 2018-2024 Localzet Group
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
 */

namespace localzet\Console\Commands;

use localzet\Server;
use support\App;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * @author walkor <walkor@workerman.net>
 * @author Ivan Zorin <ivan@zorin.space>
 */
class StopCommand extends Command
{
    protected static $defaultName = 'stop';
    protected static $defaultDescription = 'Остановить сервер. Используй -g для плавной остановки.';

    protected function configure(): void
    {
        $this
            ->addOption('graceful', 'g', InputOption::VALUE_NONE, 'Плавная остановка');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws Throwable
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
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
}
