<?php

require_once __DIR__ . '/../vendor/autoload.php';

$config = [
    'build' => [
        // Для PHAR
        'input_dir' => dirname(__DIR__),
        'output_dir' => __DIR__ . DIRECTORY_SEPARATOR . 'build',

        'exclude_pattern' => '#^(?!.*(composer.json|/.github/|/.idea/|/.git/|/.setting/|/runtime/|/vendor-bin/|/build/))(.*)$#',
        'exclude_files' => [
            '.env', 'LICENSE', 'composer.json', 'composer.lock', 'localzet.phar', 'localzet.bin'
        ],

        'phar_alias' => 'localzet',
        'phar_filename' => 'localzet.phar',
        'phar_stub' => 'example/sample.php', // Файл для require. Относительный путь, от корня `input_dir`

        'signature_algorithm' => Phar::SHA256, // Phar::MD5, Phar::SHA1, Phar::SHA256, Phar::SHA512, Phar::OPENSSL.
        'private_key_file' => '', // Для Phar::OPENSSL

        // Для бинарной сборки:
        'php_version' => 8.2,
        'custom_ini' => '
        memory_limit = 256M
        ',

        'bin_filename' => 'localzet',
    ],
];

$console = new \localzet\Console($config);
$console->run();