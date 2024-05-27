<?php

namespace localzet\Console\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ZipArchive;

class DownloadPhpCommand extends Command
{
    protected static string $defaultName = 'get:php';
    protected static string $defaultDescription = 'Скачать PHP';

    protected string $php_cli_cdn = 'ru-1.cdn.zorin.space';

    protected function configure()
    {
        $this->addArgument('version', InputArgument::OPTIONAL, 'Версия PHP (>=8.0)');
        $this->addArgument('output', InputArgument::OPTIONAL, 'Путь для сохранения файла');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $version = max($input->getArgument('version') ?? 0, 8.0);
        $output_dir = $input->getArgument('output_dir') ?? getcwd();

        $supportZip = class_exists(ZipArchive::class);
        $zipFileName = $supportZip ? "php-$version.zip" : "php-$version";

        $zipFile = rtrim($output_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $zipFileName;
        $binFile = rtrim($output_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . "php-$version";

        if (file_exists($binFile)) {
            $output->writeln("Файл уже существует: $binFile");
            return self::SUCCESS;
        }

        if (!is_file($binFile) && !is_file($zipFile)) {
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

            fwrite($client, "GET /php/$zipFileName HTTP/1.1\r\nAccept: text/html\r\nHost: $this->php_cli_cdn\r\nUser-Agent: localzet/Console\r\n\r\n");
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
                        $output->writeln("Ошибка загрузки php-$version.zip");
                        return self::FAILURE;
                    }

                    $firstLine = substr($buffer, 9, strpos($buffer, "\r\n") - 9);
                    if (!str_contains($buffer, '200 ')) {
                        $output->writeln("Ошибка загрузки php-$version.zip, $firstLine");
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
        unset($zipFileName);

        // Распаковка
        if (!is_file($binFile) && $supportZip) {
            $zip = new ZipArchive;
            $res = $zip->open($zipFile);
            if ($res === true) {
                $zip->extractTo($output_dir);
                $zip->close();
                unlink($zipFile);
                unset($zip);
            } else {
                $output->writeln("Не удалось открыть архив: $res");
                return self::FAILURE;
            }
        }
        unset($supportZip, $zipFile);

        // Добавим права на выполнение
        chmod($binFile, 0755);

        $output->writeln("\r\nЗагрузка прошла успешно!\r\nPHP v$version сохранён как $binFile\r\n");

        return self::SUCCESS;

    }
}