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

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ZipArchive;

/**
 * @author Ivan Zorin <ivan@zorin.space>
 */
class BuildBinCommand extends BuildPharCommand
{
    protected static string $defaultName = 'build:bin';
    protected static string $defaultDescription = 'Упаковать проект в BIN';

    protected float $php_version = (float)PHP_VERSION;
    protected string $php_ini = '';

    protected string $bin_filename = 'localzet.phar';
    protected ?string $bin_file = null;

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->addArgument('version', InputArgument::OPTIONAL, 'Версия PHP');

        $this->php_version = (float)$this->config('build.php_version', PHP_VERSION);
        $this->php_ini = $this->config('build.php_ini', PHP_VERSION);

        $this->bin_filename = $this->config('build.bin_filename', 'localzet.bin');
        $this->bin_file = rtrim($this->output_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $this->bin_filename;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->checkEnv();

        $output->writeln('Сборка PHAR...');

        $version = $input->getArgument('version');
        if (!$version) {
            $version = $this->php_version;
        }
        $version = $version >= 8.0 ? $version : 8.1;
        $supportZip = class_exists(ZipArchive::class);
        $microZipFileName = $supportZip ? "php$version.micro.sfx.zip" : "php$version.micro.sfx";

        $zipFile = rtrim($this->output_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $microZipFileName;
        $sfxFile = rtrim($this->output_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . "php$version.micro.sfx";
        $customIniHeaderFile = "$this->output_dir/custominiheader.bin";

        // Упаковка
        $command = new BuildPharCommand();
        $command->execute($input, $output);

        // Загрузка micro.sfx.zip
        if (!is_file($sfxFile) && !is_file($zipFile)) {
            $domain = 'download.workerman.net';
            $output->writeln("\r\nЗагрузка PHP v$version ...");
            if (extension_loaded('openssl')) {
                $client = stream_socket_client("ssl://$domain:443",
                    context: stream_context_create([
                        'ssl' => [
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                        ]
                    ])
                );
            } else {
                $client = stream_socket_client("tcp://$domain:80");
            }

            fwrite($client, "GET /php/$microZipFileName HTTP/1.0\r\nAccept: text/html\r\nHost: $domain\r\nUser-Agent: localzet/Console\r\n\r\n");
            $bodyLength = 0;
            $bodyBuffer = '';
            $lastPercent = 0;
            while (true) {
                $buffer = fread($client, 65535);
                if ($buffer !== false) {
                    $bodyBuffer .= $buffer;
                    if (!$bodyLength && $pos = strpos($bodyBuffer, "\r\n\r\n")) {
                        if (!preg_match('/Content-Length: (\d+)\r\n/', $bodyBuffer, $match)) {
                            $output->writeln("Ошибка загрузки php$version.micro.sfx.zip");
                            return self::FAILURE;
                        }
                        $firstLine = substr($bodyBuffer, 9, strpos($bodyBuffer, "\r\n") - 9);
                        if (!str_contains($bodyBuffer, '200 ')) {
                            $output->writeln("Ошибка загрузки php$version.micro.sfx.zip, $firstLine");
                            return self::FAILURE;
                        }
                        $bodyLength = (int)$match[1];
                        $bodyBuffer = substr($bodyBuffer, $pos + 4);
                    }
                }
                $receiveLength = strlen($bodyBuffer);
                $percent = ceil($receiveLength * 100 / $bodyLength);
                if ($percent != $lastPercent) {
                    echo '[' . str_pad('', (int)$percent, '=') . '>' . str_pad('', 100 - $percent) . "$percent%]";
                    echo $percent < 100 ? "\r" : "\n";
                }
                $lastPercent = $percent;
                if ($bodyLength && $receiveLength >= $bodyLength) {
                    file_put_contents($zipFile, $bodyBuffer);
                    break;
                }
                if ($buffer === false || !is_resource($client) || feof($client)) {
                    $output->writeln("Ошибка загрузки PHP $version ...");
                    return self::FAILURE;
                }
            }
        } else {
            $output->writeln("\r\nИспользуем PHP $version ...");
        }

        // Распаковка
        if (!is_file($sfxFile) && $supportZip) {
            $zip = new ZipArchive;
            $zip->open($zipFile, ZipArchive::CHECKCONS);
            $zip->extractTo($this->output_dir);
        }

        // Создание бинарника
        file_put_contents($this->bin_file, file_get_contents($sfxFile));

        // Пользовательский INI-файл
        if (!empty($this->php_ini)) {
            if (file_exists($customIniHeaderFile)) {
                unlink($customIniHeaderFile);
            }
            $f = fopen($customIniHeaderFile, 'wb');
            fwrite($f, "\xfd\xf6\x69\xe6");
            fwrite($f, pack('N', strlen($this->php_ini)));
            fwrite($f, $this->php_ini);
            fclose($f);
            file_put_contents($this->bin_file, file_get_contents($customIniHeaderFile), FILE_APPEND);
            unlink($customIniHeaderFile);
        }
        file_put_contents($this->bin_file, file_get_contents($this->phar_file), FILE_APPEND);

        // Добавим права на выполнение
        chmod($this->bin_file, 0755);

        $output->writeln("\r\nСборка прошла успешно!\r\nФайл $this->bin_filename сохранён как $this->bin_file\r\n");

        return self::SUCCESS;
    }
}