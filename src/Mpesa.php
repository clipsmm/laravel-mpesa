<?php

namespace LaravelMpesa;

use Illuminate\Support\Facades\Facade;

/**
 * Class MpesaFacade
 *
 * @package LaravelMpesa
 */
class Mpesa extends Facade
{

    /**
     * Get the registered name of the component.
     *
     * @return string
     * @codeCoverageIgnore
     */
    protected static function getFacadeAccessor(): string
    {
        self::clearResolvedInstance(MpesaSdk::class);

        return MpesaSdk::class;
    }
}
