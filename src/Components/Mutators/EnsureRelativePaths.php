<?php

namespace localzet\Console\Components\Mutators;

class EnsureRelativePaths
{
    /**
     * Ensures the given string only contains relative paths.
     *
     * @param  string  $string
     * @return string
     */
    public function __invoke($string)
    {
        if (function_exists('app') && app()->has('path.base')) {
            $string = str_replace(base_path().'/', '', $string);
        }

        return $string;
    }
}
