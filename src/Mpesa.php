<?php

namespace LaravelMpesa;

use Illuminate\Support\Facades\Facade;

/**
 * Class MpesaFacade
 */
class Mpesa extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @codeCoverageIgnore
     */
    protected static function getFacadeAccessor(): string
    {
        return MpesaSdk::class;
    }
}
