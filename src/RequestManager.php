<?php

declare(strict_types=1);

namespace LaravelMpesa;

use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class RequestManager
{
    protected array $config;

    protected ?string $accessToken = null;

    protected ?int $expiresIn = null;

    protected ?int $authenticatedAt = null;

    /**
     * Create a request manager. Authentication remains lazy for backward compatibility.
     */
    public function __construct(array $config, bool $auth = true)
    {
        $this->config = $config;
    }

    /**
     * Obtain and cache an OAuth access token for subsequent requests.
     */
    public function authenticate(): bool
    {
        $response = Http::withBasicAuth(
            $this->requiredConfig('consumer_key'),
            $this->requiredConfig('consumer_secret')
        )
            ->connectTimeout($this->connectTimeout())
            ->timeout($this->timeout())
            ->get($this->getEndpoint('/oauth/v1/generate?grant_type=client_credentials'));

        if (!$response->successful()) {
            return false;
        }

        $data = $response->json();

        if (!is_array($data) || !is_string($data['access_token'] ?? null)) {
            throw new RuntimeException('Mpesa authentication returned an invalid response.');
        }

        $this->authenticatedAt = time();
        $this->accessToken = $data['access_token'];
        $this->expiresIn = max(1, (int) ($data['expires_in'] ?? 3599));

        return true;
    }

    /**
     * Register HTTPS validation and confirmation callback URLs with Mpesa.
     *
     * @return array{0: bool, 1: array}
     */
    public function registerUrls(
        string $validationUrl,
        string $confirmationUrl,
        string $responseType = 'Cancelled'
    ): array {
        $this->assertCallbackUrl($validationUrl);
        $this->assertCallbackUrl($confirmationUrl);

        return $this->send($this->getEndpoint('/mpesa/c2b/v2/registerurl'), [
            'ResponseType' => $responseType,
            'ValidationURL' => $validationUrl,
            'ConfirmationURL' => $confirmationUrl,
            'ShortCode' => $this->requiredConfig('shortcode'),
        ]);
    }

    /**
     * Send an STK Push to a Kenyan MSISDN.
     *
     * @return array{0: bool, 1: array}
     */
    public function stkPush(
        string $receiver,
        int $amount,
        string $ref,
        string $description,
        string $callbackUrl,
        string $transactionType = 'CustomerPayBillOnline'
    ): array {
        if (preg_match('/^2547\d{8}$/', $receiver) !== 1) {
            throw new InvalidArgumentException('Receiver must use the 2547XXXXXXXX format.');
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException('STK amount must be greater than zero.');
        }

        if ($ref === '' || strlen($ref) > 100 || $description === '' || strlen($description) > 182) {
            throw new InvalidArgumentException('STK reference or description is invalid.');
        }

        $this->assertCallbackUrl($callbackUrl);
        $timestamp = date('YmdHis');
        $shortCode = $this->requiredConfig('shortcode');
        $password = base64_encode($shortCode . $this->requiredConfig('passkey') . $timestamp);

        return $this->send($this->getEndpoint('/mpesa/stkpush/v1/processrequest'), [
            'BusinessShortCode' => $shortCode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => $transactionType,
            'Amount' => $amount,
            'PartyA' => $receiver,
            'PartyB' => $shortCode,
            'PhoneNumber' => $receiver,
            'CallBackURL' => $callbackUrl,
            'AccountReference' => $ref,
            'TransactionDesc' => $description,
            'Remark' => $description,
        ]);
    }

    /**
     * Query the status of a completed or initiated Mpesa transaction.
     *
     * @return array{0: bool, 1: array}
     */
    public function transactionStatus(
        string $identifier,
        string $resultUrl,
        string $timeoutUrl,
        string $identifierType = '4',
        string $remarks = 'Transaction status query',
        string $occasion = 'TransactionStatus'
    ): array {
        $identifier = trim($identifier);
        $identifierType = trim($identifierType);
        $remarks = trim($remarks) ?: 'Transaction status query';
        $occasion = trim($occasion) ?: 'TransactionStatus';

        if ($identifier === '' || strlen($identifier) > 100) {
            throw new InvalidArgumentException('Transaction status identifier is invalid.');
        }

        if (!in_array($identifierType, ['1', '2', '3', '4'], true)) {
            throw new InvalidArgumentException('Transaction status identifier type is invalid.');
        }

        if (strlen($remarks) > 100 || strlen($occasion) > 100) {
            throw new InvalidArgumentException('Transaction status remarks or occasion is invalid.');
        }

        $this->assertCallbackUrl($resultUrl);
        $this->assertCallbackUrl($timeoutUrl);

        return $this->send($this->getEndpoint('/mpesa/transactionstatus/v1/query'), [
            'Initiator' => $this->requiredConfig('initiator_name'),
            'SecurityCredential' => $this->requiredConfig('initiator_password'),
            'CommandID' => 'TransactionStatusQuery',
            'TransactionID' => $identifier,
            'PartyA' => $this->requiredConfig('shortcode'),
            'IdentifierType' => $identifierType,
            'ResultURL' => $resultUrl,
            'QueueTimeOutURL' => $timeoutUrl,
            'Remarks' => $remarks,
            'Occasion' => $occasion,
        ]);
    }

    /**
     * Resolve a relative Daraja API path against the live or sandbox host.
     */
    public function getEndpoint(string $url): string
    {
        if (parse_url($url, PHP_URL_SCHEME) !== null || str_contains($url, '..')) {
            throw new InvalidArgumentException('Mpesa endpoint must be a relative API path.');
        }

        $host = $this->isLive() ? 'https://api.safaricom.co.ke' : 'https://sandbox.safaricom.co.ke';

        return $host . '/' . ltrim($url, '/');
    }

    /**
     * Determine whether a cached access token is still usable.
     */
    public function isAuthenticated(): bool
    {
        if (!$this->accessToken || !$this->authenticatedAt || !$this->expiresIn) {
            return false;
        }

        return time() < ($this->authenticatedAt + $this->expiresIn - 30);
    }

    /**
     * Read a value from this manager's immutable configuration snapshot.
     */
    public function getConfig(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->config) ? $this->config[$key] : $default;
    }

    /**
     * Send an authenticated JSON request to Mpesa.
     *
     * @return array{0: bool, 1: array}
     */
    private function send(string $url, array $payload): array
    {
        if (!$this->isAuthenticated() && !$this->authenticate()) {
            throw new RuntimeException('Unable to authenticate with Mpesa.');
        }

        $response = Http::withToken((string) $this->accessToken)
            ->connectTimeout($this->connectTimeout())
            ->timeout($this->timeout())
            ->post($url, $payload);
        $data = $response->json();

        return [$response->successful(), is_array($data) ? $data : []];
    }

    private function isLive(): bool
    {
        return $this->getConfig('status') === 'live';
    }

    private function requiredConfig(string $key): string
    {
        $value = $this->getConfig($key);

        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("Missing Mpesa configuration: {$key}.");
        }

        return $value;
    }

    private function assertCallbackUrl(string $url): void
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Mpesa callback URL is invalid.');
        }

        if ($scheme !== 'https' && ($this->isLive() || !$this->getConfig('allow_insecure_callbacks', false))) {
            throw new InvalidArgumentException('Mpesa callback URL must use HTTPS.');
        }
    }

    private function connectTimeout(): int
    {
        return max(1, (int) $this->getConfig('connect_timeout', 5));
    }

    private function timeout(): int
    {
        return max(1, (int) $this->getConfig('timeout', 15));
    }
}
