<?php

declare(strict_types=1);

namespace LaravelMpesa\Tests\Unit;

use Illuminate\Support\Facades\Http;
use LaravelMpesa\RequestManager;
use LaravelMpesa\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

class SecurityCredentialTest extends TestCase
{
    private static ?string $certPath = null;

    private static ?string $privateKeyPem = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        $csr = openssl_csr_new(['commonName' => 'Mpesa Test Certificate'], $privateKey);
        $certificate = openssl_csr_sign($csr, null, $privateKey, 1);

        openssl_x509_export($certificate, $certificatePem);
        openssl_pkey_export($privateKey, $privateKeyPem);

        self::$privateKeyPem = $privateKeyPem;
        self::$certPath = tempnam(sys_get_temp_dir(), 'mpesa-cert-').'.pem';
        file_put_contents(self::$certPath, $certificatePem);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$certPath !== null && is_file(self::$certPath)) {
            unlink(self::$certPath);
        }

        parent::tearDownAfterClass();
    }

    #[Test]
    public function test_encryptsInitiatorPasswordWithConfiguredCertificate(): void
    {
        Http::fake([
            '*/oauth/v1/generate*' => Http::response(['access_token' => 'access-token', 'expires_in' => 3599]),
            '*/mpesa/transactionstatus/v1/query' => Http::response(['ResponseCode' => '0']),
        ]);

        $manager = new RequestManager([
            'status' => 'sandbox',
            'consumer_key' => 'test-key',
            'consumer_secret' => 'test-secret',
            'shortcode' => '174379',
            'initiator_name' => 'test-initiator',
            'initiator_password' => 'super-secret-password',
            'security_credential_cert' => self::$certPath,
        ]);

        $manager->transactionStatus(
            'UGEHJB6GMF',
            'https://example.test/status/result',
            'https://example.test/status/timeout'
        );

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/mpesa/transactionstatus/v1/query')) {
                return false;
            }

            $privateKey = openssl_pkey_get_private((string) self::$privateKeyPem);
            openssl_private_decrypt(
                base64_decode((string) $request['SecurityCredential']),
                $decrypted,
                $privateKey,
                OPENSSL_PKCS1_PADDING
            );

            return $decrypted === 'super-secret-password';
        });
    }

    #[Test]
    public function test_throwsWhenCertificateIsMissing(): void
    {
        Http::fake([
            '*/oauth/v1/generate*' => Http::response(['access_token' => 'access-token', 'expires_in' => 3599]),
        ]);

        $manager = new RequestManager([
            'status' => 'sandbox',
            'consumer_key' => 'test-key',
            'consumer_secret' => 'test-secret',
            'shortcode' => '174379',
            'initiator_name' => 'test-initiator',
            'initiator_password' => 'super-secret-password',
            'security_credential_cert' => '/nonexistent/cert.pem',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('certificate is missing or unreadable');

        $manager->transactionStatus(
            'UGEHJB6GMF',
            'https://example.test/status/result',
            'https://example.test/status/timeout'
        );
    }

    #[Test]
    public function test_securityCredentialOverrideBypassesEncryption(): void
    {
        Http::fake([
            '*/oauth/v1/generate*' => Http::response(['access_token' => 'access-token', 'expires_in' => 3599]),
            '*/mpesa/transactionstatus/v1/query' => Http::response(['ResponseCode' => '0']),
        ]);

        $manager = new RequestManager([
            'status' => 'sandbox',
            'consumer_key' => 'test-key',
            'consumer_secret' => 'test-secret',
            'shortcode' => '174379',
            'initiator_name' => 'test-initiator',
            'security_credential' => 'precomputed-credential',
        ]);

        $manager->transactionStatus(
            'UGEHJB6GMF',
            'https://example.test/status/result',
            'https://example.test/status/timeout'
        );

        Http::assertSent(fn ($request) => str_contains($request->url(), '/mpesa/transactionstatus/v1/query')
            && $request['SecurityCredential'] === 'precomputed-credential');
    }

    #[Test]
    public function test_bundledCertificatesAreValidRsaPublicKeys(): void
    {
        foreach (['SandboxCertificate.cer', 'ProductionCertificate.cer'] as $filename) {
            $path = dirname(__DIR__, 2).'/certs/'.$filename;

            $this->assertFileExists($path);

            $publicKey = openssl_pkey_get_public((string) file_get_contents($path));
            $this->assertNotFalse($publicKey, "{$filename} did not yield a usable RSA public key.");

            $details = openssl_pkey_get_details($publicKey);
            $this->assertSame(OPENSSL_KEYTYPE_RSA, $details['type']);
        }
    }

    #[Test]
    public function test_defaultsToBundledSandboxCertificateWhenNoOverrideIsConfigured(): void
    {
        Http::fake([
            '*/oauth/v1/generate*' => Http::response(['access_token' => 'access-token', 'expires_in' => 3599]),
            '*/mpesa/transactionstatus/v1/query' => Http::response(['ResponseCode' => '0']),
        ]);

        $manager = new RequestManager([
            'status' => 'sandbox',
            'consumer_key' => 'test-key',
            'consumer_secret' => 'test-secret',
            'shortcode' => '174379',
            'initiator_name' => 'test-initiator',
            'initiator_password' => 'super-secret-password',
        ]);

        $manager->transactionStatus(
            'UGEHJB6GMF',
            'https://example.test/status/result',
            'https://example.test/status/timeout'
        );

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/mpesa/transactionstatus/v1/query')) {
                return false;
            }

            // 2048-bit RSA ciphertext is always 256 bytes, i.e. 344 base64 characters.
            return is_string($request['SecurityCredential']) && strlen($request['SecurityCredential']) === 344;
        });
    }

    #[Test]
    public function test_defaultsToBundledProductionCertificateWhenStatusIsLive(): void
    {
        Http::fake([
            '*/oauth/v1/generate*' => Http::response(['access_token' => 'access-token', 'expires_in' => 3599]),
            '*/mpesa/transactionstatus/v1/query' => Http::response(['ResponseCode' => '0']),
        ]);

        $manager = new RequestManager([
            'status' => 'live',
            'consumer_key' => 'test-key',
            'consumer_secret' => 'test-secret',
            'shortcode' => '174379',
            'initiator_name' => 'test-initiator',
            'initiator_password' => 'super-secret-password',
        ]);

        $manager->transactionStatus(
            'UGEHJB6GMF',
            'https://example.test/status/result',
            'https://example.test/status/timeout'
        );

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/mpesa/transactionstatus/v1/query')) {
                return false;
            }

            return is_string($request['SecurityCredential']) && strlen($request['SecurityCredential']) === 344;
        });
    }
}
