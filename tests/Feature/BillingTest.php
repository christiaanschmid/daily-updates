<?php

use App\Livewire\Settings\Plan;
use App\Livewire\Updates\Index;
use App\Models\DailyUpdate;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Paddle\Cashier;
use Laravel\Paddle\Subscription;
use Livewire\Livewire;

const MONTHLY_PRICE = 'pri_01testmonthly0000000000000';
const YEARLY_PRICE = 'pri_01testyearly00000000000000';

beforeEach(function () {
    // Tests never reach Paddle: anything not faked with Cashier::fake() fails.
    Http::preventStrayRequests();

    config([
        'services.paddle.prices.monthly' => MONTHLY_PRICE,
        'services.paddle.prices.yearly' => YEARLY_PRICE,
        'cashier.client_side_token' => 'test_clientsidetoken000000000000',
        'cashier.sandbox' => true,
    ]);
});

/**
 * A user who already has a Paddle customer record, so checkout needs no API call.
 */
function payingCustomer(array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $user->customer()->create(['paddle_id' => 'ctm_test'.$user->id, 'name' => $user->name, 'email' => $user->email]);

    return $user;
}

/**
 * Give the user a subscription in the given state, as Paddle's webhooks would.
 */
function subscribe(User $user, string $status = 'active', array $attributes = [], string $price = MONTHLY_PRICE): Subscription
{
    $subscription = $user->subscriptions()->create([
        'type' => 'default',
        'paddle_id' => 'sub_test'.$user->id,
        'status' => $status,
        ...$attributes,
    ]);

    $subscription->items()->create([
        'product_id' => 'pro_test',
        'price_id' => $price,
        'status' => $status,
        'quantity' => 1,
    ]);

    return $subscription;
}

/**
 * Post a webhook the way Paddle does, signed with the given secret.
 */
function postWebhook(array $payload, ?string $secret, ?int $timestamp = null)
{
    $body = json_encode($payload);
    $timestamp ??= time();
    $headers = ['Content-Type' => 'application/json'];

    if ($secret !== null) {
        $headers['Paddle-Signature'] = "ts={$timestamp};h1=".hash_hmac('sha256', "{$timestamp}:{$body}", $secret);
    }

    return test()->call('POST', '/paddle/webhook', [], [], [], test()->transformHeadersToServerVars($headers), $body);
}

function subscriptionCreatedPayload(User $user): array
{
    return [
        'event_type' => 'subscription.created',
        'data' => [
            'id' => 'sub_from_webhook',
            'customer_id' => $user->customer->paddle_id,
            'status' => 'trialing',
            'next_billed_at' => now()->addDays(30)->toIso8601String(),
            'custom_data' => ['subscription_type' => 'default'],
            'items' => [[
                'status' => 'trialing',
                'quantity' => 1,
                'price' => ['id' => MONTHLY_PRICE, 'product_id' => 'pro_test'],
            ]],
        ],
    ];
}

test('a signed webhook from Paddle records the subscription', function () {
    config(['cashier.webhook_secret' => 'pdl_ntfset_testsecret']);
    $user = payingCustomer();

    postWebhook(subscriptionCreatedPayload($user), 'pdl_ntfset_testsecret')->assertOk();

    expect($user->fresh()->hasCloudPlan())->toBeTrue()
        ->and($user->fresh()->subscription()->onTrial())->toBeTrue();
});

test('a webhook without a valid signature is refused', function (?string $signedWith) {
    config(['cashier.webhook_secret' => 'pdl_ntfset_testsecret']);
    $user = payingCustomer();

    postWebhook(subscriptionCreatedPayload($user), $signedWith)->assertForbidden();

    expect($user->fresh()->hasCloudPlan())->toBeFalse();
})->with([
    'no signature' => [null],
    'the wrong secret' => ['pdl_ntfset_someoneelse'],
]);

test('an old, replayed webhook is refused', function () {
    config(['cashier.webhook_secret' => 'pdl_ntfset_testsecret']);
    $user = payingCustomer();

    postWebhook(subscriptionCreatedPayload($user), 'pdl_ntfset_testsecret', time() - 60)->assertForbidden();

    expect($user->fresh()->hasCloudPlan())->toBeFalse();
});

test('every webhook is refused while the webhook secret is missing', function () {
    config(['cashier.webhook_secret' => null]);
    $user = payingCustomer();

    // Without a secret, Cashier's own route would accept this; an empty key is easy to forge.
    postWebhook(subscriptionCreatedPayload($user), '')->assertStatus(503);

    expect($user->fresh()->hasCloudPlan())->toBeFalse();
});

test('a subscription in its trial unlocks the full history', function () {
    $this->travelTo(now()->setDate(2026, 11, 17));
    $user = payingCustomer();
    subscribe($user, 'trialing', ['trial_ends_at' => now()->addDays(30)]);
    DailyUpdate::factory()->for($user)->create(['date' => '2026-09-01', 'done' => '<p>Much older work</p>']);

    $this->actingAs($user);

    Livewire::test(Index::class)->assertSee('Much older work');
});

test('a failed payment means the free plan until the payment method is updated', function () {
    $user = payingCustomer();
    subscribe($user, 'past_due');

    expect($user->hasCloudPlan())->toBeFalse();

    $this->actingAs($user);

    Livewire::test(Plan::class)
        ->assertSeeHtml('data-test="plan-past-due"')
        ->assertSee('Update payment method');
});

test('a free user sees both plans with the trial', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('plan.edit'))
        ->assertOk()
        ->assertSee('data-test="plan-free"', false)
        ->assertSee('$5')
        ->assertSee('$50')
        ->assertSee('Start 30-day free trial');
});

test('subscribing opens Paddle\'s checkout overlay for the chosen price', function (string $period, string $price) {
    Cashier::fake();
    $user = payingCustomer();
    $this->actingAs($user);

    $component = Livewire::test(Plan::class)->call('subscribe', $period);

    $script = collect($component->effects['xjs'] ?? [])->pluck('expression')->implode("\n");

    expect($script)
        ->toContain('window.openPaddleCheckout(')
        ->toContain($price)
        ->toContain('"displayMode":"overlay"')
        ->toContain($user->customer->paddle_id)
        ->toContain('test_clientsidetoken000000000000')
        ->not->toContain((string) config('cashier.api_key') ?: 'no-api-key-configured');
})->with([
    'monthly' => ['monthly', MONTHLY_PRICE],
    'yearly' => ['yearly', YEARLY_PRICE],
]);

test('only the monthly and yearly prices can be bought', function () {
    $this->actingAs(payingCustomer());

    Livewire::test(Plan::class)->call('subscribe', 'lifetime')->assertNotFound();
});

test('the trial and its end date are shown', function () {
    $this->travelTo(now()->setDate(2026, 11, 17));
    $user = payingCustomer();
    subscribe($user, 'trialing', ['trial_ends_at' => now()->setDate(2026, 12, 17)], YEARLY_PRICE);
    $this->actingAs($user);

    Livewire::test(Plan::class)
        ->assertSeeHtml('data-test="plan-subscribed"')
        ->assertSee('$50 a year')
        ->assertSee('Free trial until December 17, 2026');
});

test('a subscription can be canceled and then kept after all', function () {
    $user = payingCustomer();
    $subscription = subscribe($user);
    $endsAt = now()->addDays(12)->startOfSecond();

    Cashier::fake()
        ->response("subscriptions/{$subscription->paddle_id}/cancel", [
            'status' => 'active',
            'scheduled_change' => ['action' => 'cancel', 'effective_at' => $endsAt->toIso8601String()],
        ])
        ->response("subscriptions/{$subscription->paddle_id}", ['status' => 'active', 'scheduled_change' => null]);

    $this->actingAs($user);

    Livewire::test(Plan::class)
        ->call('cancel')
        ->assertSee('Canceled. You keep the cloud plan until');

    expect($subscription->fresh()->onGracePeriod())->toBeTrue()
        ->and($user->fresh()->hasCloudPlan())->toBeTrue();

    Livewire::test(Plan::class)
        ->call('keepSubscription')
        ->assertDontSee('Canceled.');

    expect($subscription->fresh()->onGracePeriod())->toBeFalse();
});

test('a subscription in its trial can be canceled', function () {
    $user = payingCustomer();
    $subscription = subscribe($user, 'trialing', ['trial_ends_at' => now()->addDays(20)]);

    Cashier::fake()->response("subscriptions/{$subscription->paddle_id}/cancel", [
        'status' => 'trialing',
        'scheduled_change' => ['action' => 'cancel', 'effective_at' => now()->addDays(20)->toIso8601String()],
    ]);

    $this->actingAs($user);

    Livewire::test(Plan::class)->call('cancel')->assertHasNoErrors();

    expect($subscription->fresh()->ends_at)->not->toBeNull();
});

test('an account given the cloud plan by hand has nothing to pay', function () {
    $this->actingAs(User::factory()->cloudPlan()->create());

    Livewire::test(Plan::class)
        ->assertSeeHtml('data-test="plan-granted"')
        ->assertDontSeeHtml('data-test="subscribe-monthly"');
});

test('the plan page waits for Paddle after checkout', function () {
    $this->actingAs(payingCustomer());

    Livewire::withQueryParams(['checkout' => 'done'])
        ->test(Plan::class)
        ->assertSeeHtml('data-test="plan-finishing"');
});

test('the desktop app\'s plan page offers cloud sync instead of billing', function () {
    config(['nativephp-internal.running' => true]);

    $this->get(route('plan.edit'))
        ->assertOk()
        ->assertSee('data-test="sync-off"', false)
        ->assertDontSee('data-test="plan-free"', false);

    Livewire::test(Plan::class)->call('subscribe', 'monthly')->assertNotFound();
});

test('the payment link page opens Paddle\'s checkout for a transaction', function () {
    $this->get(route('pay', ['_ptxn' => 'txn_test']))
        ->assertOk()
        ->assertSee('https://cdn.paddle.com/paddle/v2/paddle.js', false)
        ->assertSee("Paddle.Environment.set('sandbox')", false)
        ->assertSee('test_clientsidetoken000000000000', false);

    $this->get(route('pay'))
        ->assertOk()
        ->assertDontSee('cdn.paddle.com', false);
});
