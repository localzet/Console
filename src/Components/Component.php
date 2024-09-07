<?php

namespace localzet\Console\Components;

use localzet\Console\OutputStyle;
use localzet\Console\QuestionHelper;
use ReflectionClass;
use Symfony\Component\Console\Helper\SymfonyQuestionHelper;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use function Termwind\render;
use function Termwind\renderUsing;

abstract class Component
{
    /**
     * The output style implementation.
     *
     * @var OutputStyle
     */
    protected $output;

    /**
     * The list of mutators to apply on the view data.
     *
     * @var array<int, callable(string): string>
     */
    protected $mutators;

    /**
     * Creates a new component instance.
     *
     * @param OutputStyle|OutputInterface $output
     * @return void
     */
    public function __construct($output)
    {
        $this->output = $output;
    }

    /**
     * Renders the given view.
     *
     * @param string $view
     * @param array $vars
     * @param int $verbosity
     * @return void
     */
    protected function renderView($view, $vars, $verbosity)
    {
        renderUsing($this->output);

        extract($vars);
        ob_start();

        try {
            $file = __DIR__ . "/../../resources/views/components/$view.php";
            if (file_exists($file)) include $file;
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        render((string)ob_get_clean(), $verbosity);
    }

    /**
     * Mutates the given data with the given set of mutators.
     *
     * @param array<int, string>|string $data
     * @param array<int, callable(string): string> $mutators
     * @return array<int, string>|string
     */
    protected function mutate($data, $mutators)
    {
        foreach ($mutators as $mutator) {
            $mutator = new $mutator;

            if (is_iterable($data)) {
                foreach ($data as $key => $value) {
                    $data[$key] = $mutator($value);
                }
            } else {
                $data = $mutator($data);
            }
        }

        return $data;
    }

    /**
     * Eventually performs a question using the component's question helper.
     *
     * @param callable $callable
     * @return mixed
     */
    protected function usingQuestionHelper($callable)
    {
        $property = with(new ReflectionClass(OutputStyle::class))
            ->getParentClass()
            ->getProperty('questionHelper');

        $currentHelper = $property->isInitialized($this->output)
            ? $property->getValue($this->output)
            : new SymfonyQuestionHelper();

        $property->setValue($this->output, new QuestionHelper);

        try {
            return $callable();
        } finally {
            $property->setValue($this->output, $currentHelper);
        }
    }
}
