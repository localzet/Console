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

/** Renders a bounded pseudographic dashboard that can be passed to Terminal::draw(). */
final class Dashboard
{
    /**
     * @param array<string, array{value:string, detail?:string, percent?:int|float}> $metrics
     * @param array<string, list<string>> $sections
     * @return list<string>
     */
    public function render(
        string $title,
        array $metrics = [],
        array $sections = [],
        string $footer = '',
        int $width = 80,
        int $height = 24
    ): array {
        $width = max(4, min(300, $width));
        $height = max(3, min(120, $height));
        $innerWidth = $width - 4;

        $content = [Theme::ink(Utf8::clean($title), 'accent', true)];
        foreach ($metrics as $label => $metric) {
            if (!is_array($metric) || !isset($metric['value']) || !is_string($metric['value'])) {
                continue;
            }
            $content[] = $this->metric((string)$label, $metric, $innerWidth);
        }

        foreach ($sections as $heading => $rows) {
            if (!is_array($rows)) {
                continue;
            }
            $content[] = Theme::ink('▰ ' . Utf8::clean((string)$heading), 'accent', true);
            foreach ($rows as $row) {
                if (!is_string($row) && !is_numeric($row)) {
                    continue;
                }
                $content[] = Theme::ink('  • ', 'muted') . Utf8::clean((string)$row);
            }
        }
        if ($footer !== '') {
            $content[] = Theme::ink(Utf8::clean($footer), 'muted');
        }

        $contentLimit = $height - 2;
        if (count($content) > $contentLimit) {
            $content = array_slice($content, 0, $contentLimit);
            $content[$contentLimit - 1] = Theme::ink('…', 'muted');
        }

        $lines = ['┌' . str_repeat('─', $width - 2) . '┐'];
        foreach ($content as $line) {
            $lines[] = '│ ' . Utf8::pad($line, $innerWidth) . ' │';
        }
        while (count($lines) < $height - 1) {
            $lines[] = '│' . str_repeat(' ', $width - 2) . '│';
        }
        $lines[] = '└' . str_repeat('─', $width - 2) . '┘';

        return $lines;
    }

    /** @param array{value:string, detail?:string, percent?:int|float} $metric */
    private function metric(string $label, array $metric, int $width): string
    {
        $label = Utf8::clean($label);
        $value = Utf8::clean($metric['value']);
        $rawDetail = $metric['detail'] ?? '';
        $detail = is_string($rawDetail) || is_numeric($rawDetail) ? Utf8::clean((string)$rawDetail) : '';
        $line = Theme::ink($label, 'muted') . '  ' . Theme::ink($value, 'text', true);

        if (isset($metric['percent']) && is_numeric($metric['percent'])) {
            $percent = max(0.0, min(100.0, (float)$metric['percent']));
            $barWidth = max(3, min(16, $width - Utf8::width($label) - Utf8::width($value) - Utf8::width($detail) - 8));
            $filled = (int)round($barWidth * $percent / 100);
            $tone = $percent >= 90 ? 'bad' : ($percent >= 75 ? 'warn' : 'success');
            $line .= '  ' . Theme::ink(str_repeat('━', $filled), $tone)
                . Theme::ink(str_repeat('·', $barWidth - $filled), 'dim');
        }

        if ($detail !== '') {
            $line .= '  ' . Theme::ink($detail, 'muted');
        }

        return $line;
    }
}
