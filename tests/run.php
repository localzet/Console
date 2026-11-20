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
use localzet\Console\Tui\Dashboard;
use localzet\Console\Tui\Terminal;
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

$defaultApp = new Console();
check($defaultApp->has('list') && $defaultApp->has('help'), 'Internal commands install on the default application');

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
check(Utf8::clean("first\nsecond\tthird\r\nfourth") === 'first second third fourth', 'Line breaks and tabs are normalized in terminal text');
$clippedControlText = Utf8::clipAnsi("\033[31mfirst\nsecond\tthird\007\033[0m", 40);
check(!str_contains($clippedControlText, "\n") && !str_contains($clippedControlText, "\t"), 'ANSI clipping prevents embedded terminal line breaks');
check(str_contains($clippedControlText, "\033[31m"), 'ANSI clipping preserves valid styling sequences');
$clippedTerminalCommands = Utf8::clipAnsi("\033[2Jclear\033[10Amove", 40);
check(
    !str_contains($clippedTerminalCommands, "\033[2J") && !str_contains($clippedTerminalCommands, "\033[10A"),
    'ANSI clipping removes terminal commands other than styling'
);

$terminal = new Terminal();
$terminalReflection = new ReflectionClass($terminal);
$activeProperty = $terminalReflection->getProperty('active');
$activeProperty->setValue($terminal, true);
$ttyProperty = $terminalReflection->getProperty('tty');
$tty = fopen('php://memory', 'r+');
$ttyProperty->setValue($terminal, $tty);
$oldSttyProperty = $terminalReflection->getProperty('oldStty');
$oldSttyProperty->setValue($terminal, 'saved-settings');
$terminal->open();
check($ttyProperty->getValue($terminal) === $tty, 'Opening an active terminal must not replace its handle');
check($oldSttyProperty->getValue($terminal) === 'saved-settings', 'Opening an active terminal must not overwrite saved settings');
fclose($tty);

$drawTerminal = new Terminal();
$drawTerminalReflection = new ReflectionClass($drawTerminal);
$drawTty = fopen('php://memory', 'r+');
$drawTerminalReflection->getProperty('tty')->setValue($drawTerminal, $drawTty);
$drawTerminalReflection->getProperty('size')->setValue($drawTerminal, [40, 1]);
$drawTerminal->draw(["\033[2Jclear\033[10Amove\033[32mstyled\033[0m"]);
rewind($drawTty);
$drawOutput = stream_get_contents($drawTty);
check(
    !str_contains($drawOutput, "\033[2J") && !str_contains($drawOutput, "\033[10A"),
    'Terminal::draw removes injected screen and cursor commands'
);
check(str_contains($drawOutput, "\033[32m"), 'Terminal::draw retains safe ANSI styling');
fclose($drawTty);

$dashboard = new Dashboard();
$lines = $dashboard->render(
    "Runtime\033]0;untrusted\007",
    ['CPU' => ['value' => '42%', 'percent' => 101, 'detail' => '8 cores']],
    ['Workers' => ['worker A ready', 'worker B ready']],
    'Q quit · R refresh',
    60,
    10
);
check(count($lines) === 10, 'Dashboard rendering respects the terminal height');
check(array_reduce($lines, static fn(bool $valid, string $line): bool => $valid && Utf8::width($line) === 60, true), 'Dashboard rows fit the terminal width');
$plainDashboard = Utf8::clean(implode("\n", $lines));
check(str_contains($plainDashboard, 'Runtime') && !str_contains($plainDashboard, 'untrusted'), 'Dashboard strips terminal control sequences from titles');
check(str_contains($plainDashboard, 'worker A ready') && str_contains($plainDashboard, 'Q quit'), 'Dashboard renders sections and a footer');

$unsafeDashboard = $dashboard->render(
    "first\nsecond",
    ['CPU' => ['value' => "42%\nlow", 'detail' => "8 cores\tready"]],
    ["Services\nready" => ["worker\none"]],
    "Q\tquit",
    40,
    8
);
check(
    array_reduce($unsafeDashboard, static fn(bool $valid, string $line): bool => $valid && !str_contains($line, "\n") && !str_contains($line, "\r"), true),
    'Dashboard fields cannot split a rendered row'
);
check(
    array_reduce($unsafeDashboard, static fn(bool $valid, string $line): bool => $valid && Utf8::width($line) === 40, true),
    'Sanitized dashboard rows retain the requested width'
);

echo "All Console integration checks passed.\n";
