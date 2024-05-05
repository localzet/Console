<?php

declare(strict_types=1);

/**
 * @package     Localzet Console library
 * @link        https://github.com/localzet/Console
 *
 * @author      Ivan Zorin <ivan@zorin.space>
 * @copyright   Copyright (c) 2016-2024 Zorin Projects
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
class HelpCommand extends \Symfony\Component\Console\Command\HelpCommand
{
    protected static string $defaultName = 'help';
    protected static string $defaultDescription = 'Отображает справку о командах';

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->ignoreValidationErrors();

        $this
            ->setName('help')
            ->setDefinition([
                new InputArgument('command_name', InputArgument::OPTIONAL, 'Название команды', 'help'),
                new InputOption('format', null, InputOption::VALUE_REQUIRED, 'Формат вывода (txt, xml, json, or md)', 'txt'),
                new InputOption('raw', null, InputOption::VALUE_NONE, 'Вывести необработанную справку'),
            ])
            ->setDescription('Отображает справку о командах')
            ->setHelp(<<<'EOF'
Команда <info>%command.name%</info> отображает справку о любой команде:

    "<info>%command.full_name% list</info>" - справка о команде "list"

Вы можете получить вывод в разных форматах, используя опцию <comment>--format</comment>:

    <info>%command.full_name% --format=xml list</info>

Для отображения списка доступных команд используйте команду "<info>list</info>".
EOF
            );
    }
}
