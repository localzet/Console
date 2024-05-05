<?php

namespace localzet\Console\Commands;

class Command extends \Symfony\Component\Console\Command\Command
{
    private ?array $config = [];

    /**
     * @param array|null $config
     * @return $this
     */
    public function setConfig(?array $config = []): static
    {
        $this->config = $config;

        return $this;
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
}