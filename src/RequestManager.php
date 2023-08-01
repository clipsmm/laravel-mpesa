<?php


namespace LaravelMpesa;


use Illuminate\Support\Facades\Http;

class RequestManager
{
    protected array $config;
    protected ?string $access_token = null;
    protected $expires_in = null;
    protected $authed_at = null;

    public function __construct(array $config, bool $auth = true)
    {
        $this->config = $config;
    }

    /**
     * Get & store access token to authentication future requests
     *
     * @throws \Exception
     */
    public function authenticate(): bool
    {
        $key = $this->getConfig('consumer_key');
        $secret = $this->getConfig('consumer_secret');
        $secretToken = base64_encode("{$key}:{$secret}");
        $url = $this->getEndpoint("/oauth/v1/generate?grant_type=client_credentials");

        try {
            $response  = Http::withToken($secretToken, "Basic")
                ->get($url);
            $data = json_decode($response->body(), true);

            if ($response->successful()) {
                $this->authed_at = time();
                $this->access_token = $data['access_token'];
                $this->expires_in = $data['expires_in'];

                return true;
            }

            return false;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Register confirmation and validation endpoints
     *
     * @param $validationUrl / Url to receive validation results
     * @param $confirmationUrl / Url to receive payment confirmations
     * @param string $responseType / Default response type
     * @throws \Exception
     */
    public function registerUrls(string $validationUrl, string $confirmationUrl, string $responseType = "Cancelled")
    {
        $url  = $this->getEndpoint("/mpesa/c2b/v2/registerurl");
        $shortCode = $this->getConfig('shortcode');

        $payload = array(
            'ResponseType' => $responseType,
            'ValidationURL' => $validationUrl,
            'ConfirmationURL' => $confirmationUrl,
            'ShortCode' => $shortCode,
        );

        return $this->send($url, $payload);
    }

    /**
     * Send STK Push
     *
     * @param $receiver / Phone number receiving payment, +2547xxxxxxx
     * @param $amount / Amount to paid
     * @param $ref / Invoice number
     * @param $description / Reason for transaction
     * @param $callbackUrl / Callback ur
     * @param string $transactionType / Type of transaction
     */
    public function stkPush(string $receiver, int $amount, string $ref, string $description, string $callbackUrl, string $transactionType = "CustomerPayBillOnline")
    {
        $url  = $this->getEndpoint("mpesa/stkpush/v1/processrequest");

        $timestamp = date("Ymdhis");
        $shortCode = $this->getConfig('shortcode');
        $passKey = $this->getConfig('passkey');
        $password = base64_encode($shortCode . $passKey . $timestamp);

        $payload = array(
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
            'Remark' => $description
        );

        return $this->send($url, $payload);
    }

    /**
     * Send http request
     *
     * @param $url
     * @param array $payload
     * @return array[bool, array]
     * @throws \Exception
     */
    private function send($url, array $payload): array
    {
        if (!$this->isAuthenticated()) {
            $this->authenticate();
        }

        $response = Http::withToken($this->access_token)
            ->post($url, $payload);

        $data = json_decode($response->body(), true);

        return [$response->successful(), $data];
    }

    /**
     * Get full url
     *
     * @param $url
     * @return string
     */
    public function getEndpoint($url): string
    {
        if ($this->isLive()) {
            return "https://api.safaricom.co.ke/{$url}";
        }

        return "https://sandbox.safaricom.co.ke/{$url}";
    }

    /**
     * Check if is authed and is still valid
     *
     * @return boolean
     */
    public function isAuthenticated(): bool
    {
        // session has not been authed yet
        if (!$this->access_token) {
            return false;
        }

        return time() < ($this->authed_at + $this->expires_in);
    }

    /**
     * @param $key
     * @param $default
     * @return mixed
     */
    public function getConfig($key, $default = null)
    {
        try {
            $val =  $this->config[$key];

            if (!$val) {
                $val = env($key, $default);
            }

            return $val;
        } catch (\Exception $exception) {
            return $default;
        }
    }

    private function isLive(): bool
    {
        return $this->getConfig('status', null) === "live";
    }
}
