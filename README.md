# Localzet Console

Localzet Console is a PHP command-line application layer built on Symfony Console. It keeps Symfony commands as its default API, adds an optional bridge for Laravel Artisan commands, and provides small Unicode and ANSI utilities for terminal interfaces.

## Requirements

- PHP 8.4.1 or newer
- Composer 2

The package tracks Symfony Console 8.1 and Illuminate Console 13. The PHP requirement follows Symfony Console 8.1.

## Installation

```sh
composer require localzet/console
```

## Create an application

```php
<?php

require __DIR__ . '/vendor/autoload.php';

$console = new localzet\Console\Console([
    'name' => 'Example CLI',
    'version' => '1.0.0',
]);

exit($console->run());
```

Commands extending `localzet\Console\Commands\Command` receive the application config array in their constructor. Their static `$defaultName` and `$defaultDescription` define Symfony command metadata. Use `installCommands($directory, $namespace)` to discover commands from an application directory.

## Laravel command bridge

Symfony remains the default application and command API. To register Illuminate commands, explicitly provide the Laravel container and event dispatcher:

```php
$console->useIlluminate($laravelApplication);
$console->addIlluminateCommand(new App\Console\Commands\ExampleCommand());
```

Illuminate commands use Laravel's command lifecycle and therefore need a bootstrapped Laravel application. They can also be discovered with `installCommands()` after `useIlluminate()` has been configured.

## Terminal helpers

`localzet\Console\Tui\Utf8` measures terminal cell width, clips ANSI-styled lines without splitting wide characters, pads output, and removes control sequences from untrusted text. `Dashboard` renders bounded pseudographic panels with metrics, gauges, sections and a footer; pass its lines to `Terminal::draw()`. `Terminal` provides an opt-in `/dev/tty` full-screen renderer for interactive Unix terminals; call `close()` in `finally` so terminal settings are restored.

```php
$terminal = new localzet\Console\Tui\Terminal();
$dashboard = new localzet\Console\Tui\Dashboard();
$terminal->open();
try {
    [$width, $height] = $terminal->size();
    $terminal->draw($dashboard->render(
        'Localzet Console',
        ['CPU' => ['value' => '42%', 'percent' => 42, 'detail' => '8 cores']],
        ['Workers' => ['worker A ready', 'worker B ready']],
        'Q quit · R refresh',
        $width,
        $height
    ));
} finally {
    $terminal->close();
}
```

## Verification

```sh
composer install
composer run check
```

The test suite exercises Symfony and Illuminate command execution, terminal text handling and dashboard layout bounds. It does not claim to emulate a real terminal; interactive rendering should also be checked in the target terminal emulator.

## License

AGPL-3.0-or-later. See [LICENSE](LICENSE).
