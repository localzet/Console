<?php

declare(strict_types=1);

/**
 * @package     Localzet Console library
 * @link        https://github.com/localzet/Console
 *
 * @author      Ivan Zorin <ivan@zorin.space>
 * @copyright   Copyright (c) 2018-2024 Zorin Projects S.P.
 * @license     GNU Affero General Public License, version 3
 *
 *              This program is free software: you can redistribute it and/or modify
 *              it under the terms of the GNU Affero General Public License as
 *              published by the Free Software Foundation, either version 3 of the
 *              License, or (at your option) any later version.
 *
 *              This program is distributed in the hope that it will be useful,
 *              but WITHOUT ANY WARRANTY; without even the implied warranty of
 *              MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 *              GNU Affero General Public License for more details.
 *
 *              You should have received a copy of the GNU Affero General Public License
 *              along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace localzet\Console\Commands;

use Symfony\Component\Console\{Input\InputArgument, Input\InputOption};

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Ivan Zorin <ivan@zorin.space>
 */
class ListCommand extends \Symfony\Component\Console\Command\ListCommand
{
    protected static string $defaultName = 'list';
    protected static string $defaultDescription = 'Список команд';

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('list')
            ->setDefinition([
                new InputArgument('namespace', InputArgument::OPTIONAL, 'Имя пространства'),
                new InputOption('raw', null, InputOption::VALUE_NONE, 'Вывести необработанный список команд'),
                new InputOption('format', null, InputOption::VALUE_REQUIRED, 'Выходной формат (txt, xml, json или md)', 'txt'),
                new InputOption('short', null, InputOption::VALUE_NONE, 'Пропустить описание аргументов команд'),
            ])
            ->setDescription('Список команд')
            ->setHelp(<<<'EOF'
Команда <info>%command.name%</info> отображает список всех команд:

  <info>%command.full_name%</info>

Также можно получить список команд в определённом пространстве, например:

  <info>%command.full_name% build</info>

Вы можете получить вывод в разных форматах, используя опцию <comment>--format</comment>:

  <info>%command.full_name% --format=xml</info>

Также можно получить необработанный список команд (полезно для встроенных средств запуска команд):

  <info>%command.full_name% --raw</info>
EOF
            );
    }
}
