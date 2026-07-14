<?php

declare(strict_types=1);

namespace LaravelMpesa\Tests\Unit;

use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Event;
use LaravelMpesa\Events\Callbacks\StkCallbackFailed;
use LaravelMpesa\Events\Callbacks\StkCallbackReceived;
use LaravelMpesa\Events\Callbacks\StkCallbackSucceeded;
use LaravelMpesa\Tests\Fakes\CustomStkCallbackController;
use LaravelMpesa\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class StkCallbackControllerTest extends TestCase
{
    #[Test]
    public function test_stkSuccessCallbackFiresReceivedAndSucceededEvents(): void
    {
        Event::fake();

        $payload = $this->fixture('stk_success_callback.json');

        $this->postJson('/signal/ingress/pulse', $payload)
            ->assertOk()
            ->assertJsonPath('accepted', true)
            ->assertJsonPath('checkoutRequestId', 'ws_CO_14072026222032600702997218')
            ->assertJsonPath('resultCode', 0);

        Event::assertDispatched(StkCallbackReceived::class, static fn(StkCallbackReceived $event): bool =>
            $event->callback->merchantRequestId === 'ff3e-4fa4-abc0-8eb3aa92c0d998768'
            && $event->callback->checkoutRequestId === 'ws_CO_14072026222032600702997218'
            && $event->callback->metadataValue('Amount') === 5
            && $event->callback->metadataValue('MpesaReceiptNumber') === 'UGEHJB6GMF'
            && $event->callback->metadataValue('PhoneNumber') === 254702997218);
        Event::assertDispatched(StkCallbackSucceeded::class);
        Event::assertNotDispatched(StkCallbackFailed::class);
    }

    #[Test]
    public function test_stkCancelledCallbackFiresReceivedAndFailedEvents(): void
    {
        Event::fake();

        $payload = $this->fixture('stk_cancelled_callback.json');

        $this->postJson('/signal/ingress/pulse', $payload)
            ->assertOk()
            ->assertJsonPath('accepted', true)
            ->assertJsonPath('checkoutRequestId', 'ws_CO_14072026222709770702997218')
            ->assertJsonPath('resultCode', 1032);

        Event::assertDispatched(StkCallbackReceived::class);
        Event::assertDispatched(StkCallbackFailed::class, static fn(StkCallbackFailed $event): bool =>
            $event->callback->resultCode === 1032
            && $event->callback->resultDescription === 'Request Cancelled by user.'
            && $event->callback->metadata === []);
        Event::assertNotDispatched(StkCallbackSucceeded::class);
    }

    #[Test]
    public function test_stkCallbackRouteAndControllerCanBeOverriddenFromConfig(): void
    {
        $this->app['config']->set('mpesa.callbacks.path_prefix', '/ignored/callbacks');
        $this->app['config']->set('mpesa.callbacks.routes.stk', '/custom/signal/pulse');
        $this->app['config']->set('mpesa.callbacks.controllers.stk', CustomStkCallbackController::class);

        $this->reloadCallbackRoutesFromConfig();

        $this->postJson('/custom/signal/pulse', $this->fixture('stk_success_callback.json'))
            ->assertOk()
            ->assertJsonPath('accepted', true)
            ->assertJsonPath('customController', true)
            ->assertJsonPath('checkoutRequestId', 'ws_CO_14072026222032600702997218');

        $this->postJson('/signal/ingress/pulse', $this->fixture('stk_success_callback.json'))
            ->assertNotFound();
    }

    #[Test]
    public function test_stkCallbackRejectsRequestsFromDisallowedIp(): void
    {
        Event::fake();

        $this->app['config']->set('mpesa.callbacks.allowed_ips', ['196.201.214.200']);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->postJson('/signal/ingress/pulse', $this->fixture('stk_success_callback.json'))
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden.');

        Event::assertNotDispatched(StkCallbackReceived::class);
        Event::assertNotDispatched(StkCallbackSucceeded::class);
        Event::assertNotDispatched(StkCallbackFailed::class);
    }

    #[Test]
    public function test_stkCallbackAcceptsRequestsFromAllowedIp(): void
    {
        Event::fake();

        $this->app['config']->set('mpesa.callbacks.allowed_ips', ['196.201.214.200']);

        $this->withServerVariables(['REMOTE_ADDR' => '196.201.214.200'])
            ->postJson('/signal/ingress/pulse', $this->fixture('stk_success_callback.json'))
            ->assertOk()
            ->assertJsonPath('accepted', true);

        Event::assertDispatched(StkCallbackReceived::class);
        Event::assertDispatched(StkCallbackSucceeded::class);
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