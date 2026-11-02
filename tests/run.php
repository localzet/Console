<?php

/**
 * @package     Localzet Console
 * @link        https://github.com/localzet/Console
 *
 * @author      Ivan Zorin <creator@localzet.com>
 * @copyright   Copyright © 2016-2026 Localzet Group. All rights reserved.
 * @license     https://www.gnu.org/licenses/agpl-3.0 GNU Affero General Public License v3.0
 *
 *              This program is free software: you can redistribute it and/or modify
 *              it under the terms of the GNU Affero General Public License as published
 *              by the Free Software Foundation, either version 3 of the License, or
 *              (at your option) any later version.
 *
 *              This program is distributed in the hope that it will be useful,
 *              but WITHOUT ANY WARRANTY; without even the implied warranty of
 *              MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 *              GNU Affero General Public License for more details.
 *
 *              You should have received a copy of the GNU Affero General Public License
 *              along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Illuminate\Console\Command as IlluminateCommand;
use Illuminate\Container\Container;
use localzet\Console;
use localzet\Console\Commands\Command;
use localzet\Console\Tui\Utf8;
use Symfony\Component\Console\Tester\CommandTester;

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

final class TestSymfonyCommand extends Command
{
    protected static string $defaultName = 'test:symfony';

    public function __construct()
    {
        parent::__construct([]);
    }

    protected function execute(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output): int
    {
        $output->writeln('symfony-ok');
        return self::SUCCESS;
    }
}

final class TestIlluminateCommand extends IlluminateCommand
{
    protected $signature = 'test:illuminate';
    protected $description = 'Integration test command';

    public function handle(): int
    {
        $this->line('illuminate-ok');
        return self::SUCCESS;
    }
}

$app = new Console(['name' => 'Test Console', 'version' => '1.0.0'], false);
$app->addCommand(new TestSymfonyCommand());
$symfonyTester = new CommandTester($app->find('test:symfony'));
$symfonyTester->execute([]);
check(str_contains($symfonyTester->getDisplay(), 'symfony-ok'), 'Symfony command runs through Localzet Console');

$container = new class extends Container {
    public function runningUnitTests(): bool { return true; }
};
$app->useIlluminate($container);
$app->addIlluminateCommand(new TestIlluminateCommand());
$illuminateTester = new CommandTester($app->find('test:illuminate'));
$illuminateTester->execute([]);
check(str_contains($illuminateTester->getDisplay(), 'illuminate-ok'), 'Illuminate command runs through Localzet Console');

$failed = false;
try {
    (new Console([], false))->addIlluminateCommand(new TestIlluminateCommand());
} catch (LogicException) {
    $failed = true;
}
check($failed, 'Illuminate commands require explicit container integration');

check(Utf8::width('сервер') === 6, 'UTF-8 text width uses terminal cells');
check(Utf8::width('界') === 2, 'Wide CJK glyph occupies two terminal cells');
check(Utf8::width("\033[31mтест\033[0m") === 4, 'ANSI styling does not affect terminal width');
check(Utf8::width(Utf8::clipAnsi('界界x', 3)) === 2, 'ANSI clipping never splits a wide glyph');
check(!str_contains(Utf8::clean("safe\033]0;title\007text"), "\033"), 'Untrusted terminal control sequences are removed');

echo "All Console integration checks passed.\n";
