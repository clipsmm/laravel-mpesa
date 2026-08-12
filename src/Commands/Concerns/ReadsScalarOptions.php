<?php

declare(strict_types=1);

namespace LaravelMpesa\Commands\Concerns;

trait ReadsScalarOptions
{
    protected function optionString(string $name): string
    {
        $value = $this->option($name);

        return is_scalar($value) ? (string) $value : '';
    }
}
