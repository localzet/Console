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

namespace localzet\Console\Tui;

/** Minimal interactive terminal driver with reversible terminal state. */
final class Terminal
{
    private mixed $tty = null;
    private string $oldStty = '';
    private bool $active = false;
    private array $size = [80, 24];
    private array $lastLines = [];

    public function open(): void
    {
        $this->tty = @fopen('/dev/tty', 'r+');
        if (!is_resource($this->tty)) {
            throw new \RuntimeException('Interactive mode requires a terminal.');
        }

        $this->oldStty = trim((string) shell_exec('stty -g < /dev/tty 2>/dev/null'));
        if ($this->oldStty === '') {
            fclose($this->tty);
            $this->tty = null;
            throw new \RuntimeException('Cannot read terminal settings.');
        }

        $this->active = true;
        register_shutdown_function(fn () => $this->close());
        shell_exec('stty -icanon -echo -ixon min 0 time 0 < /dev/tty 2>/dev/null');
        stream_set_blocking($this->tty, false);
        $this->write("\033[?1049h\033[?25l\033[0m\033[2J");
        $this->resize();
    }

    public function close(): void
    {
        if (!$this->active) {
            return;
        }

        $this->active = false;
        $this->write("\033[0m\033[?25h\033[?1049l");
        shell_exec('stty ' . escapeshellarg($this->oldStty) . ' < /dev/tty 2>/dev/null');
        if (is_resource($this->tty)) {
            fclose($this->tty);
        }
    }

    /** @return array{0:int,1:int} Width and height in terminal cells. */
    public function resize(): array
    {
        $raw = trim((string) shell_exec('stty size < /dev/tty 2>/dev/null'));
        if (preg_match('/^(\d+)\s+(\d+)$/', $raw, $matches)) {
            $this->size = [max(1, min(300, (int) $matches[2])), max(1, min(120, (int) $matches[1]))];
        }
        return $this->size;
    }

    /** @return array{0:int,1:int} */
    public function size(): array
    {
        return $this->size;
    }

    public function draw(array $lines): void
    {
        [$width, $height] = $this->size;
        $output = '';
        for ($row = 0; $row < $height; $row++) {
            $line = Utf8::clipAnsi((string) ($lines[$row] ?? ''), $width);
            if ($line !== ($this->lastLines[$row] ?? null)) {
                $output .= "\033[" . ($row + 1) . ";1H\033[2K" . $line;
            }
        }
        $this->write($output);
        $this->lastLines = array_map(
            static fn ($line): string => Utf8::clipAnsi((string) $line, $width),
            array_slice(array_pad($lines, $height, ''), 0, $height)
        );
    }

    private function write(string $data): void
    {
        if (is_resource($this->tty) && $data !== '') {
            @fwrite($this->tty, $data);
        }
    }
}
