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
 *
 *              For any questions, please contact <support@localzet.com>
 */

namespace localzet\Console\Commands;

use Exception;
use Illuminate\Support\Traits\Macroable;
use localzet\Console\Components\Factory;
use localzet\Console\OutputStyle;
use RuntimeException;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

abstract class Command extends \Symfony\Component\Console\Command\Command
{
    use Concerns\CallsCommands,
        Concerns\ConfiguresPrompts,
        Concerns\HasParameters,
        Concerns\InteractsWithIO,
        Concerns\InteractsWithSignals,
        Concerns\PromptsForMissingInput,
        Macroable;

    protected static string $defaultName;
    protected static string $defaultDescription;

    protected $name;
    protected $description;
    protected $help;
    protected $hidden = false;
    protected $aliases;

    /**
     * @var Factory
     */
    protected $components;

    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var OutputInterface|OutputStyle
     */
    protected $output;

    public function __construct(protected array $config = [])
    {
        parent::__construct($this->name);
        if ($this->description) $this->setDescription((string)$this->description);

        $this->setHelp((string)$this->help);
        $this->setHidden($this->hidden);
        if ($this->aliases) $this->setAliases((array)$this->aliases);

    }

    public static function getDefaultName(): ?string
    {
        return !empty(static::$defaultName) ? static::$defaultName : null;
    }

    public static function getDefaultDescription(): ?string
    {
        return !empty(static::$defaultDescription) ? static::$defaultDescription : null;
    }

    /**
     * @param string|null $key
     * @param mixed|null $default
     * @return mixed
     */
    public function config(string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->config;
        }

        $keyArray = explode('.', $key);
        $value = $this->config;
        foreach ($keyArray as $index) {
            if (!isset($value[$index])) {
                return $default;
            }
            $value = $value[$index];
        }

        return $value;
    }


    #[\Override]
    public function run(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output instanceof OutputStyle ? $output : new OutputStyle($input, $output);
        $this->components = new Factory($output);

        return parent::run($input, $output);
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $method = method_exists($this, 'handle') ? 'handle' : '__invoke';

        try {
            return (int)$this->$method;
        } catch (Exception $e) {
            $this->components->error($e->getMessage());
            return static::FAILURE;
        }
    }

    protected function resolveCommand($command)
    {
        if (is_string($command)) {
            if (!class_exists($command)) {
                return $this->getApplication()->find($command);
            }
        }

        if ($command instanceof SymfonyCommand) {
            $command->setApplication($this->getApplication());
        }

        return $command;
    }

    public function fail(Throwable|string|null $exception = null)
    {
        if (is_null($exception)) {
            $exception = 'Ошибка команды.';
        }

        if (is_string($exception)) {
            $exception = new RuntimeException($exception);
        }

        throw $exception;
    }

    #[\Override]
    public function isHidden(): bool
    {
        return $this->hidden;
    }

    #[\Override]
    public function setHidden(bool $hidden = true): static
    {
        parent::setHidden($this->hidden = $hidden);

        return $this;
    }
}