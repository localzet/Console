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

/** Unicode and ANSI helpers for terminal cell layouts. */
final class Utf8
{
    public static function width(string $value): int
    {
        $plain = self::stripAnsi($value);
        return function_exists('mb_strwidth') ? mb_strwidth($plain, 'UTF-8') : strlen($plain);
    }

    public static function clean(string $value): string
    {
        $value = self::stripAnsi($value);
        return preg_replace('/[\x00-\x08\x0b-\x1f\x7f]/', '?', $value) ?? '';
    }

    public static function clipAnsi(string $value, int $width): string
    {
        if ($width <= 0) {
            return '';
        }

        preg_match_all('/\x1b\[[0-?]*[ -\/]*[@-~]|./us', $value, $matches);
        $output = '';
        $cells = 0;
        foreach ($matches[0] ?? [] as $token) {
            if (str_starts_with($token, "\033[")) {
                $output .= $token;
                continue;
            }

            $tokenWidth = function_exists('mb_strwidth') ? mb_strwidth($token, 'UTF-8') : 1;
            if ($cells + $tokenWidth > $width) {
                break;
            }
            $output .= $token;
            $cells += $tokenWidth;
        }

        return $output . "\033[0m";
    }

    public static function pad(string $value, int $width): string
    {
        $clipped = self::clipAnsi($value, $width);
        return $clipped . str_repeat(' ', max(0, $width - self::width($clipped)));
    }

    private static function stripAnsi(string $value): string
    {
        return preg_replace('/\x1b(?:\[[0-?]*[ -\/]*[@-~]|\][^\x07]*(?:\x07|\x1b\\\\)|.)/s', '', $value) ?? '';
    }
}
