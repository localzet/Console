# Localzet Console

Localzet Console — слой для PHP CLI-приложений на базе Symfony Console. По умолчанию он использует команды Symfony, дополнительно умеет подключать команды Laravel Artisan и содержит небольшие Unicode/ANSI-инструменты для терминальных интерфейсов.

## Требования

- PHP 8.4.1 или новее
- Composer 2

Пакет использует Symfony Console 8.1 и Illuminate Console 13. Минимальная версия PHP соответствует Symfony Console 8.1.

## Установка

```sh
composer require localzet/console
```

## Создание приложения

```php
<?php

require __DIR__ . '/vendor/autoload.php';

$console = new localzet\Console\Console([
    'name' => 'Example CLI',
    'version' => '1.0.0',
]);

exit($console->run());
```

Команды на базе `localzet\Console\Commands\Command` получают конфигурационный массив приложения в конструкторе. Статические `$defaultName` и `$defaultDescription` задают имя и описание команды. Для поиска команд в каталоге приложения используйте `installCommands($directory, $namespace)`.

## Подключение команд Laravel

По умолчанию приложение и команды используют API Symfony. Для регистрации команд Illuminate передайте загруженное приложение Laravel или его контейнер: он должен реализовывать `ContainerContract` и предоставлять зависимости, необходимые командам. Отдельно передавать диспетчер событий не нужно:

```php
$console->useIlluminate($laravelApplication);
$console->addIlluminateCommand(new App\Console\Commands\ExampleCommand());
```

Командам Illuminate нужен загруженный Laravel application: он отвечает за их жизненный цикл. После вызова `useIlluminate()` команды Illuminate также доступны для поиска через `installCommands()`.

## Терминальные инструменты

`localzet\Console\Tui\Utf8` измеряет ширину текста в ячейках терминала, обрезает строки с ANSI-оформлением без разделения широких символов, дополняет строки и удаляет управляющие последовательности из непроверенного текста. `Dashboard` строит псевдографическую панель с показателями, индикаторами, разделами и подсказкой; её строки можно передать в `Terminal::draw()`. `Terminal` предоставляет полноэкранный вывод через `/dev/tty` для интерактивных Unix-терминалов; вызывайте `close()` в `finally`, чтобы вернуть настройки терминала.

```php
$terminal = new localzet\Console\Tui\Terminal();
$dashboard = new localzet\Console\Tui\Dashboard();
$terminal->open();
try {
    [$width, $height] = $terminal->size();
    $terminal->draw($dashboard->render(
        'Localzet Console',
        ['ЦП' => ['value' => '42%', 'percent' => 42, 'detail' => '8 ядер']],
        ['Workers' => ['worker A готов', 'worker B готов']],
        'Q — выход · R — обновить',
        $width,
        $height
    ));
} finally {
    $terminal->close();
}
```

Если установлен Localzet Server, команда `status --live` открывает полноэкранную панель состояния сервера. Она раз в секунду читает результат `status --json` через текущий CLI entry point. Нажмите `Q`, чтобы выйти, или `R`, чтобы обновить данные сразу. Одноразовый `status --json` по-прежнему подходит для скриптов и систем мониторинга.

## Проверка

```sh
composer install
composer run check
```

Набор проверок запускает команды Symfony и Illuminate, проверяет обработку текста и границы раскладки панели. В Linux при наличии util-linux `script` он также запускает live-команду в псевдотерминале. Цвета и символы дополнительно проверьте в целевом эмуляторе терминала.

## Лицензия

AGPL-3.0-or-later. См. [LICENSE](LICENSE).
