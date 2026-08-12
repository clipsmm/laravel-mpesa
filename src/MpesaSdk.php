<?php

declare(strict_types=1);

namespace LaravelMpesa;

use Illuminate\Support\Arr;
use InvalidArgumentException;

class MpesaSdk
{
    protected string $app;

    /** @var array<string, mixed> */
    protected array $config;

    protected RequestManager $requestManager;

    /**
     * Create an SDK instance for a configured Mpesa application.
     *
     * @param  array<string, mixed>  $opts
     */
    public function __construct(?string $app = null, array $opts = [])
    {
        $this->app = $app ?: (string) config('mpesa.default');
        $applicationConfig = config("mpesa.apps.{$this->app}");

        if (! is_array($applicationConfig)) {
            throw new InvalidArgumentException("Mpesa application [{$this->app}] is not configured.");
        }

        $baseConfig = Arr::except((array) config('mpesa'), ['apps']);
        $this->config = array_merge($baseConfig, $applicationConfig, $opts);
        $this->requestManager = new RequestManager($this->config);
    }

    /**
     * Create a request manager for the selected configured Mpesa application.
     */
    public static function instance(?string $app = null): RequestManager
    {
        return (new self($app))->requestManager();
    }

    /**
     * The configured application name this SDK instance was built for.
     */
    public function app(): string
    {
        return $this->app;
    }

    /**
     * The request manager backing this SDK instance.
     */
    public function requestManager(): RequestManager
    {
        return $this->requestManager;
    }

    /**
     * Obtain and cache an OAuth access token for subsequent requests.
     */
    public function authenticate(): bool
    {
        return $this->requestManager->authenticate();
    }

    /**
     * Determine whether a cached access token is still usable.
     */
    public function isAuthenticated(): bool
    {
        return $this->requestManager->isAuthenticated();
    }

    /**
     * Register HTTPS validation and confirmation callback URLs with Mpesa.
     *
     * @return array{0: bool, 1: array<string, mixed>}
     */
    public function registerUrls(
        string $validationUrl,
        string $confirmationUrl,
        string $responseType = 'Cancelled'
    ): array {
        return $this->requestManager->registerUrls($validationUrl, $confirmationUrl, $responseType);
    }

    /**
     * Send an STK Push to a Kenyan MSISDN.
     *
     * @return array{0: bool, 1: array<string, mixed>}
     */
    public function stkPush(
        string $receiver,
        int $amount,
        string $ref,
        string $description,
        string $callbackUrl,
        string $transactionType = 'CustomerPayBillOnline'
    ): array {
        return $this->requestManager->stkPush($receiver, $amount, $ref, $description, $callbackUrl, $transactionType);
    }

    /**
     * Query the status of a completed or initiated Mpesa transaction.
     *
     * @return array{0: bool, 1: array<string, mixed>}
     */
    public function transactionStatus(
        string $identifier,
        string $resultUrl,
        string $timeoutUrl,
        string $identifierType = '4',
        string $remarks = 'Transaction status query',
        string $occasion = 'TransactionStatus'
    ): array {
        return $this->requestManager->transactionStatus(
            $identifier,
            $resultUrl,
            $timeoutUrl,
            $identifierType,
            $remarks,
            $occasion,
        );
    }

    /**
     * Resolve a relative Daraja API path against the live or sandbox host.
     */
    public function getEndpoint(string $url): string
    {
        return $this->requestManager->getEndpoint($url);
    }
}
