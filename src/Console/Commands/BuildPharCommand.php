<?php

namespace localzet\Console\Commands;

use Composer\InstalledVersions;
use Phar;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Ivan Zorin <ivan@zorin.space>
 */
class BuildPharCommand extends Command
{
    protected static string $defaultName = 'build:phar';
    protected static string $defaultDescription = 'Упаковать проект в PHAR';

    protected string $input_dir = '';
    protected string $output_dir = '';

    protected array $exclude_files = [];
    protected string $exclude_pattern = '';

    protected string $phar_alias = 'localzet';
    protected string $phar_filename = 'localzet.phar';
    protected string $phar_stub = 'master';

    protected int $signature_algorithm = Phar::SHA256;
    protected string $private_key_file = '';

    protected ?string $phar_file = null;

    protected function configure()
    {
        $this->input_dir = $this->config('build.input_dir');
        $this->output_dir = $this->config('build.output_dir', '');

        $this->exclude_files = $this->config('build.exclude_files', []);
        $this->exclude_pattern = $this->config('build.exclude_pattern', '');

        $this->phar_alias = $this->config('build.phar_alias', 'localzet');
        $this->phar_filename = $this->config('build.phar_filename', 'localzet.phar');
        $this->phar_stub = $this->config('build.phar_stub', 'master');

        $this->signature_algorithm = $this->config('build.signature_algorithm', Phar::SHA256);
        $this->private_key_file = $this->config('build.private_key_file', '');

        $this->phar_file = rtrim($this->output_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $this->phar_filename;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->input_dir) throw new RuntimeException('Для сборки нужен каталог с входными данными (build.input_dir)');
        if (!$this->output_dir) throw new RuntimeException('Для сборки нужен каталог для выходных данных (build.output_dir)');
        if (!$this->phar_stub) throw new RuntimeException('Для сборки нужен файл инициализации (build.phar_stub), он будет вызываться при обращении к phar');

        $this->checkEnv();

        if (!file_exists($this->output_dir) && !is_dir($this->output_dir)) {
            if (!mkdir($this->output_dir, 0777, true)) {
                throw new RuntimeException("Не удалось создать выходной каталог phar-файла. Пожалуйста, проверьте разрешения.");
            }
        }

        if ($this->phar_file && file_exists($this->phar_file)) {
            unlink($this->phar_file);
        }

        $phar = new Phar($this->phar_file, 0, $this->phar_alias);
        $phar->startBuffering();

        if (!in_array($this->signature_algorithm, [Phar::MD5, Phar::SHA1, Phar::SHA256, Phar::SHA512, Phar::OPENSSL])) {
            throw new RuntimeException('Доступные алгоритмы подписи: Phar::MD5, Phar::SHA1, Phar::SHA256, Phar::SHA512, и Phar::OPENSSL.');
        }

        if ($this->signature_algorithm === Phar::OPENSSL) {
            if (!file_exists($this->private_key_file)) {
                throw new RuntimeException("Если вы выбрали алгоритм Phar::OPENSSL - необходимо задать файл закрытого ключа (build.private_key_file).");
            }

            $private = openssl_get_privatekey(file_get_contents($this->private_key_file));
            $pkey = '';
            openssl_pkey_export($private, $pkey);
            $phar->setSignatureAlgorithm($this->signature_algorithm, $pkey);
        } else {
            $phar->setSignatureAlgorithm($this->signature_algorithm);
        }

        $phar->buildFromDirectory($this->input_dir, $this->exclude_pattern);

        // Исключаем соответствующие файлы
        $exclude_command_files = [
            'AppCreateCommand.php',
            'BuildBinCommand.php',
            'BuildPharCommand.php',
            'DisableCommand.php',
            'EnableCommand.php',
            'InstallCommand.php',
            'MakeBootstrapCommand.php',
            'MakeCommandCommand.php',
            'MakeControllerCommand.php',
            'MakeMiddlewareCommand.php',
            'MakeModelCommand.php',
            'PluginCreateCommand.php',
            'PluginDisableCommand.php',
            'PluginEnableCommand.php',
            'PluginExportCommand.php',
            'PluginInstallCommand.php',
            'PluginUninstallCommand.php',
            'PluginUpdateCommand.php',
            'UpdateCommand.php',
        ];
        $exclude_command_files = array_map(function ($cmd_file) {
            return rtrim(InstalledVersions::getInstallPath('localzet/console'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $cmd_file;
        }, $exclude_command_files);

        $exclude_files = array_unique(array_merge($exclude_command_files, $this->exclude_files));
        foreach ($exclude_files as $file) {
            if ($phar->offsetExists($file)) {
                $phar->delete($file);
            }
        }

        $output->writeln('Сбор файлов завершен, начинаю добавлять файлы в PHAR.');

        $phar->setStub("#!/usr/bin/env php
<?php
define('IN_PHAR', true);
Phar::mapPhar('$this->phar_alias');
require 'phar://$this->phar_alias/$this->phar_stub';
__HALT_COMPILER();
");

        $output->writeln('Запись файлов в PHAR архив и сохранение изменений.');

        $phar->stopBuffering();
        unset($phar);
        return self::SUCCESS;
    }

    /**
     * @throws RuntimeException
     */
    public function checkEnv(): void
    {
        if (!class_exists(Phar::class, false)) {
            throw new RuntimeException("Для сборки пакета требуется расширение PHAR");
        }

        if (ini_get('phar.readonly')) {
            throw new RuntimeException(
                "В конфигурации php включен параметр 'phar.readonly'! Для сборки отключите его или повторите команду с флагом: 'php -d phar.readonly=0 {command}'"
            );
        }
    }

}