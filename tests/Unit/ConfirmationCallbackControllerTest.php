<?php

declare(strict_types=1);

namespace LaravelMpesa\Tests\Unit;

use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Event;
use LaravelMpesa\Events\Callbacks\C2bConfirmationReceived;
use LaravelMpesa\Tests\Fakes\CustomConfirmationCallbackController;
use LaravelMpesa\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ConfirmationCallbackControllerTest extends TestCase
{
    #[Test]
    public function test_confirmationCallbackFiresReceivedEvent(): void
    {
        Event::fake();

        $payload = $this->fixture('c2b_confirmation_callback.json');

        $this->postJson('/signal/ingress/echo', $payload)
            ->assertOk()
            ->assertJsonPath('ResultCode', 0)
            ->assertJsonPath('ResultDesc', 'Success');

        Event::assertDispatched(C2bConfirmationReceived::class, static fn(C2bConfirmationReceived $event): bool =>
            $event->confirmation->transId === 'RKTQDM7108'
            && $event->confirmation->transAmount === 10.00
            && $event->confirmation->billRefNumber === 'invoice008'
            && $event->confirmation->msisdn === '254712345678'
            && $event->confirmation->orgAccountBalance === 49197.00);
    }

    #[Test]
    public function test_confirmationRouteAndControllerCanBeOverriddenFromConfig(): void
    {
        $this->app['config']->set('mpesa.callbacks.path_prefix', '/ignored/callbacks');
        $this->app['config']->set('mpesa.callbacks.routes.confirmation', '/custom/signal/echo');
        $this->app['config']->set('mpesa.callbacks.controllers.confirmation', CustomConfirmationCallbackController::class);

        $this->reloadCallbackRoutesFromConfig();

        $this->postJson('/custom/signal/echo', $this->fixture('c2b_confirmation_callback.json'))
            ->assertOk()
            ->assertJsonPath('customController', true)
            ->assertJsonPath('transId', 'RKTQDM7108');

        $this->postJson('/signal/ingress/echo', $this->fixture('c2b_confirmation_callback.json'))
            ->assertNotFound();
    }

    #[Test]
    public function test_confirmationCallbackRejectsRequestsFromDisallowedIp(): void
    {
        Event::fake();

        $this->app['config']->set('mpesa.callbacks.allowed_ips', ['196.201.214.200']);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->postJson('/signal/ingress/echo', $this->fixture('c2b_confirmation_callback.json'))
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden.');

        Event::assertNotDispatched(C2bConfirmationReceived::class);
    }

    #[Test]
    public function test_confirmationCallbackAcceptsRequestsFromAllowedIp(): void
    {
        Event::fake();

        $this->app['config']->set('mpesa.callbacks.allowed_ips', ['196.201.214.200']);

        $this->withServerVariables(['REMOTE_ADDR' => '196.201.214.200'])
            ->postJson('/signal/ingress/echo', $this->fixture('c2b_confirmation_callback.json'))
            ->assertOk()
            ->assertJsonPath('ResultCode', 0);

        Event::assertDispatched(C2bConfirmationReceived::class);
    }

    /** @return array<string, mixed> */
    private function fixture(string $filename): array
    {
        $contents = file_get_contents(__DIR__.'/../Fixtures/mpesa/'.$filename);
        $decoded = is_string($contents) ? json_decode($contents, true) : null;

        $this->assertIsArray($decoded);

        return $decoded;
    }

    private function reloadCallbackRoutesFromConfig(): void
    {
        $this->app['router']->setRoutes(new RouteCollection());

        /** @var string $path */
        $path = realpath(__DIR__.'/../../routes/callbacks.php');

        require $path;
    }
}
