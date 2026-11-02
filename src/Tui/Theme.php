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

/** Small ANSI palette for full-screen terminal views. */
final class Theme
{
    private const COLORS = [
        'text' => '218;228;233',
        'muted' => '126;145;162',
        'accent' => '75;190;230',
        'success' => '105;205;151',
        'warn' => '235;180;94',
        'bad' => '225;104;111',
        'edge' => '66;82;97',
        'dim' => '86;103;117',
    ];

    public static function ink(string $text, string $tone = 'text', bool $bold = false): string
    {
        $rgb = self::COLORS[$tone] ?? self::COLORS['text'];
        return "\033[" . ($bold ? '1;' : '') . "38;2;{$rgb}m{$text}\033[0m";
    }
}
