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

    protected float $php_version = 8.3;
    protected string $php_ini = 'memory_limit = 256M';

    protected string $bin_filename = 'localzet';
    protected ?string $bin_file = null;

    protected string $php_ini_file = '';
    protected string $php_cli_cdn = 'ru-1.cdn.zorin.space';

    /**
     * @return void
     */
    protected function configure(): void
    {
        parent::configure();
        $this->addArgument('version', InputArgument::OPTIONAL, 'Версия PHP');

        $this->php_version = $this->config('build.php_version', $this->php_version);
        $this->php_ini = $this->config('build.php_ini', $this->php_ini);

        $this->bin_filename = $this->config('build.bin_filename', $this->bin_filename);
        $this->bin_file = rtrim($this->output_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $this->bin_filename;

        $this->php_ini_file = rtrim($this->output_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'custominiheader.bin';
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Упаковка
        parent::execute($input, $output);

        $version = $input->getArgument('version') ?? $this->php_version;
        $version = (float) max($version, 8.1);

        $supportZip = class_exists(ZipArchive::class);
        $microZipFileName = $supportZip ? "php-$version-micro.zip" : "php-$version-micro";

        $zipFile = rtrim($this->output_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $microZipFileName;
        $sfxFile = rtrim($this->output_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . "php-$version-micro";

        // Загрузка micro.sfx.zip
        if (!is_file($sfxFile) && !is_file($zipFile)) {
            $output->writeln("\r\nЗагрузка PHP v$version ...");

            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ]
            ]);

            $client = @stream_socket_client("ssl://$this->php_cli_cdn:443", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);
            unset($context);
            if (!$client) {
                $output->writeln("Ошибка подключения: $errstr ($errno)");
                return self::FAILURE;
            }

            fwrite($client, "GET /php/$microZipFileName HTTP/1.1\r\nAccept: text/html\r\nHost: $this->php_cli_cdn\r\nUser-Agent: localzet/Console\r\n\r\n");
            $bodyLength = 0;
            $file = fopen($zipFile, 'w');
            $lastPercent = 0;
            while (!feof($client)) {
                $buffer = fread($client, 65535);
                if ($buffer === false) {
                    $output->writeln("Ошибка чтения данных: $php_errormsg");
                    return self::FAILURE;
                }

                if ($bodyLength) {
                    fwrite($file, $buffer);
                } else if ($pos = strpos($buffer, "\r\n\r\n")) {
                    if (!preg_match('/Content-Length: (\d+)\r\n/', $buffer, $match)) {
                        $output->writeln("Ошибка загрузки php-$version-micro.zip");
                        return self::FAILURE;
                    }

                    $firstLine = substr($buffer, 9, strpos($buffer, "\r\n") - 9);
                    if (!str_contains($buffer, '200 ')) {
                        $output->writeln("Ошибка загрузки php-$version-micro.zip, $firstLine");
                        return self::FAILURE;
                    }

                    $bodyLength = (int)$match[1];
                    fwrite($file, substr($buffer, $pos + 4));
                }

                $receiveLength = ftell($file);
                $percent = ceil($receiveLength * 100 / $bodyLength);
                if ($percent != $lastPercent) {
                    echo '[' . str_pad('', (int)$percent, '=') . '>' . str_pad('', 100 - (int)$percent) . "$percent%]";
                    echo $percent < 100 ? "\r" : "\n";
                }

                $lastPercent = $percent;
                if ($bodyLength && $receiveLength >= $bodyLength) {
                    break;
                }
            }
            fclose($file);
            fclose($client);
            unset($client, $lastPercent, $bodyLength);
        } else {
            $output->writeln("\r\nПодключение PHP v$version ...");
        }
        unset($version, $microZipFileName);

        // Распаковка
        if (!is_file($sfxFile) && $supportZip) {
            $zip = new ZipArchive;
            $res = $zip->open($zipFile);
            if ($res === true) {
                $zip->extractTo($this->output_dir);
                $zip->close();
                unlink($zipFile);
                unset($zip);
            } else {
                $output->writeln("Не удалось открыть архив: $res");
                return self::FAILURE;
            }
        }
        unset($supportZip, $zipFile);

        $output->writeln('Сборка BIN ...');

        // Создание бинарника
        if (!copy($sfxFile, $this->bin_file)) {
            $output->writeln("Ошибка копирования файла: $php_errormsg");
            return self::FAILURE;
        }
        unset($sfxFile);

        // Пользовательский INI-файл
        if (!empty($this->php_ini)) {
            if (file_exists($this->php_ini_file)) {
                unlink($this->php_ini_file);
            }

            $f = fopen($this->php_ini_file, 'wb');
            if (!$f) {
                $output->writeln("Ошибка открытия файла: $php_errormsg");
                return self::FAILURE;
            }

            fwrite($f, "\xfd\xf6\x69\xe6");
            fwrite($f, pack('N', strlen($this->php_ini)));
            fwrite($f, $this->php_ini);
            fclose($f);
            unset($f);

            file_put_contents($this->bin_file, file_get_contents($this->php_ini_file), FILE_APPEND);
            unlink($this->php_ini_file);
        }

        // PHAR-файл
        file_put_contents($this->bin_file, file_get_contents($this->phar_file), FILE_APPEND);
        unlink($this->phar_file);

        // Добавим права на выполнение
        chmod($this->bin_file, 0755);

        $output->writeln("\r\nСборка прошла успешно!\r\nФайл $this->bin_filename сохранён как $this->bin_file\r\n");

        return self::SUCCESS;
    }
}