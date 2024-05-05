<?php

declare(strict_types=1);

/**
 * @package     Localzet Console library
 * @link        https://github.com/localzet/Console
 *
 * @author      Ivan Zorin <ivan@zorin.space>
 * @copyright   Copyright (c) 2016-2024 Zorin Projects
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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author walkor <walkor@workerman.net>
 * @author Ivan Zorin <ivan@zorin.space>
 */
class FixDisableFunctionsCommand extends Command
{
    protected static string $defaultName = 'fix-disable-functions';
    protected static string $defaultDescription = 'Исправление disable_functions в php.ini';

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $php_ini_file = php_ini_loaded_file();
        if (!$php_ini_file) {
            $output->writeln('<error>Не могу найти php.ini</error>');
            return self::FAILURE;
        }
        $output->writeln("Нашёл $php_ini_file");
        $disable_functions_str = ini_get("disable_functions");
        if (!$disable_functions_str) {
            $output->writeln('<success>ОК</success>');
        }

        $functions_required = [
            "stream_socket_server",
            "stream_socket_accept",
            "stream_socket_client",
            "pcntl_signal_dispatch",
            "pcntl_signal",
            "pcntl_alarm",
            "pcntl_fork",
            "posix_getuid",
            "posix_getpwuid",
            "posix_kill",
            "posix_setsid",
            "posix_getpid",
            "posix_getpwnam",
            "posix_getgrnam",
            "posix_getgid",
            "posix_setgid",
            "posix_initgroups",
            "posix_setuid",
            "posix_isatty",
            "proc_open",
            "proc_get_status",
            "proc_close",
            "shell_exec",
            "exec",
        ];

        $has_disbaled_functions = false;
        foreach ($functions_required as $func) {
            if (str_contains($disable_functions_str, $func)) {
                $has_disbaled_functions = true;
                break;
            }
        }

        $disable_functions = explode(",", $disable_functions_str);
        $disable_functions_removed = [];
        foreach ($disable_functions as $index => $func) {
            $func = trim($func);
            foreach ($functions_required as $func_prefix) {
                if (str_starts_with($func, $func_prefix)) {
                    $disable_functions_removed[$func] = $func;
                    unset($disable_functions[$index]);
                }
            }
        }

        $php_ini_content = file_get_contents($php_ini_file);
        if (!$php_ini_content) {
            $output->writeln("<error>$php_ini_file пуст!</error>");
            return self::FAILURE;
        }

        $new_disable_functions_str = implode(",", $disable_functions);
        $php_ini_content = preg_replace("/\ndisable_functions *?=[^\n]+/", "\ndisable_functions = $new_disable_functions_str", $php_ini_content);

        file_put_contents($php_ini_file, $php_ini_content);

        foreach ($disable_functions_removed as $func) {
            $output->write('Функция ');
            $output->write(str_pad($func, 30));
            $output->writeln('<info>активирована</info>');
        }

        $output->writeln('<info>Готово!</info>');
        return self::SUCCESS;
    }

}