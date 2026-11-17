<?php

use App\Events\UseLocalDataRequested;
use App\Livewire\Desktop\Connect;
use App\Livewire\Settings\Plan;
use App\Models\DailyUpdate;
use App\Models\DesktopConnection;
use App\Models\User;
use App\Support\CloudClient;
use App\Support\CloudMode;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Native\Desktop\Facades\App as NativeApp;
use Native\Desktop\Facades\Shell;

const SECRET = 'mac-secret-0123456789abcdefghijklmnopqrstuvwxyz0123456789ABCD';

/**
 * A connection the given user approved, ready for uploads.
 */
function approvedConnection(User $user): DesktopConnection
{
    $connection = DesktopConnection::start(SECRET);
    $connection->approveFor($user);

    return $connection->fresh();
}

function importPayload(array $updates = [], array $todos = []): array
{
    return ['secret' => SECRET, 'updates' => $updates, 'todos' => $todos];
}

// --- The hosted side -------------------------------------------------------

test('the Mac app starts a connection with a secret it keeps to itself', function () {
    $response = $this->postJson('/api/desktop/connections', ['secret' => SECRET])
        ->assertCreated()
        ->assertJsonStructure(['code', 'approve_url', 'expires_at']);

    $connection = DesktopConnection::sole();

    expect($response->json('approve_url'))->toBe(route('desktop.connect', $connection->code))
        ->and($connection->secret_hash)->not->toBe(SECRET)
        ->and($connection->hasSecret(SECRET))->toBeTrue();
});

test('every later call has to prove the secret', function () {
    $connection = approvedConnection(User::factory()->cloudPlan()->create());

    $this->postJson("/api/desktop/connections/{$connection->code}/status", ['secret' => 'wrong'])->assertNotFound();
    $this->postJson("/api/desktop/connections/{$connection->code}/import", ['secret' => 'wrong', 'updates' => [], 'todos' => []])->assertNotFound();
    $this->postJson("/api/desktop/connections/{$connection->code}/finish", ['secret' => 'wrong'])->assertNotFound();

    expect($connection->fresh()->isFinished())->toBeFalse();
});

test('the status follows approval and expiry', function () {
    $user = User::factory()->cloudPlan()->create();
    $connection = DesktopConnection::start(SECRET);
    $status = fn () => $this->postJson("/api/desktop/connections/{$connection->code}/status", ['secret' => SECRET])->json('status');

    expect($status())->toBe('pending');

    $connection->approveFor($user);
    expect($status())->toBe('approved');

    $this->travel(DesktopConnection::UPLOAD_MINUTES + 1)->minutes();
    expect($status())->toBe('expired');
});

test('the approval page asks the signed-in user to connect', function () {
    $user = User::factory()->cloudPlan()->create();
    $connection = DesktopConnection::start(SECRET);
    $this->actingAs($user);

    $this->get(route('desktop.connect', $connection->code))
        ->assertOk()
        ->assertSee('data-test="connect-ready"', false)
        ->assertSee($user->email);

    Livewire::test(Connect::class, ['code' => $connection->code])
        ->call('approve')
        ->assertSeeHtml('data-test="connect-approved"');

    expect($connection->fresh()->user_id)->toBe($user->id);
});

test('approving needs the cloud plan', function () {
    $connection = DesktopConnection::start(SECRET);
    $this->actingAs(User::factory()->create());

    Livewire::test(Connect::class, ['code' => $connection->code])
        ->assertSeeHtml('data-test="connect-needs-plan"')
        ->call('approve')
        ->assertForbidden();

    expect($connection->fresh()->isApproved())->toBeFalse();
});

test('a connection can only be approved once, by one account', function () {
    $connection = approvedConnection(User::factory()->cloudPlan()->create());
    $this->actingAs(User::factory()->cloudPlan()->create());

    Livewire::test(Connect::class, ['code' => $connection->code])
        ->assertSeeHtml('data-test="connect-taken"')
        ->call('approve')
        ->assertForbidden();
});

test('an old approval link can\'t be approved', function () {
    $connection = DesktopConnection::start(SECRET);
    $this->travel(DesktopConnection::APPROVAL_MINUTES + 1)->minutes();
    $this->actingAs(User::factory()->cloudPlan()->create());

    Livewire::test(Connect::class, ['code' => $connection->code])
        ->assertSeeHtml('data-test="connect-expired"')
        ->call('approve')
        ->assertForbidden();
});

test('the approval page needs a signed-in account', function () {
    $connection = DesktopConnection::start(SECRET);

    $this->get(route('desktop.connect', $connection->code))->assertRedirect(route('login'));
});

test('nothing can be uploaded before approval, after expiry or without the plan', function () {
    $user = User::factory()->cloudPlan()->create();
    $pending = DesktopConnection::start(SECRET);
    $this->postJson("/api/desktop/connections/{$pending->code}/import", importPayload())->assertStatus(409);

    $connection = approvedConnection($user);
    $user->forceFill(['cloud_plan_granted' => false])->save();
    $this->postJson("/api/desktop/connections/{$connection->code}/import", importPayload())->assertStatus(402);

    $user->forceFill(['cloud_plan_granted' => true])->save();
    $this->travel(DesktopConnection::UPLOAD_MINUTES + 1)->minutes();
    $this->postJson("/api/desktop/connections/{$connection->code}/import", importPayload())->assertStatus(410);
});

test('the Mac\'s logs and todos are imported into the approved account', function () {
    $user = User::factory()->cloudPlan()->create(['timezone' => 'America/Phoenix']);
    $connection = approvedConnection($user);

    $this->postJson("/api/desktop/connections/{$connection->code}/import", importPayload(
        updates: [
            ['date' => '2026-10-01', 'done' => '<ul><li><p>Shipped the Mac app</p></li></ul>', 'up_next' => null],
            ['date' => '2026-10-02', 'done' => '<p>Safe</p><script>alert(1)</script>', 'up_next' => null],
            ['date' => '2026-10-03', 'done' => '<p> </p>', 'up_next' => null],
        ],
        todos: [
            ['title' => 'Open todo', 'completed_at' => null, 'created_at' => '2026-10-01T09:00:00-07:00'],
            ['title' => 'Done todo', 'completed_at' => '2026-10-01T17:30:00-07:00', 'created_at' => '2026-09-30T09:00:00-07:00'],
        ],
    ))->assertOk()->assertExactJson(['updates' => 2, 'todos' => 2]);

    $updates = $user->dailyUpdates()->oldest('date')->get();
    expect($updates)->toHaveCount(2)
        ->and($updates[0]->done)->toContain('Shipped the Mac app')
        ->and($updates[1]->done)->toContain('Safe')->not->toContain('<script>');

    $done = $user->todos()->where('title', 'Done todo')->sole();
    expect($done->completed_at->format('Y-m-d H:i'))->toBe('2026-10-01 17:30')
        ->and($user->todos()->where('title', 'Open todo')->sole()->completed_at)->toBeNull();
});

test('a day logged in both places keeps the cloud\'s notes with the Mac\'s added below', function () {
    $user = User::factory()->cloudPlan()->create();
    DailyUpdate::factory()->for($user)->create(['date' => '2026-10-05', 'done' => '<p>Written on the web</p>']);
    DailyUpdate::factory()->for($user)->create(['date' => '2026-10-06', 'done' => '<p>Same everywhere</p>']);
    $connection = approvedConnection($user);

    $payload = importPayload(updates: [
        ['date' => '2026-10-05', 'done' => '<p>Written on the Mac</p>'],
        ['date' => '2026-10-06', 'done' => '<p>Same everywhere</p>'],
    ]);

    $this->postJson("/api/desktop/connections/{$connection->code}/import", $payload)->assertExactJson(['updates' => 1, 'todos' => 0]);

    $combined = $user->dailyUpdates()->whereDate('date', '2026-10-05')->sole()->done;
    expect($combined)->toContain('Written on the web')->toContain('Written on the Mac')
        ->and(strpos($combined, 'web'))->toBeLessThan(strpos($combined, 'Mac'));

    // A retried upload changes nothing.
    $this->postJson("/api/desktop/connections/{$connection->code}/import", $payload)->assertExactJson(['updates' => 0, 'todos' => 0]);
    expect(substr_count($user->dailyUpdates()->whereDate('date', '2026-10-05')->sole()->done, 'Written on the Mac'))->toBe(1);
});

test('todos already in the cloud aren\'t added twice', function () {
    $user = User::factory()->cloudPlan()->create();
    $user->todos()->create(['title' => 'Already here']);
    $connection = approvedConnection($user);

    $this->postJson("/api/desktop/connections/{$connection->code}/import", importPayload(todos: [
        ['title' => 'Already here', 'completed_at' => null],
        ['title' => 'New one', 'completed_at' => null],
    ]))->assertExactJson(['updates' => 0, 'todos' => 1]);

    expect($user->todos()->count())->toBe(2);
});

test('uploads are checked like anything typed in', function () {
    $connection = approvedConnection(User::factory()->cloudPlan()->create());

    $this->postJson("/api/desktop/connections/{$connection->code}/import", importPayload(
        updates: [['date' => 'not-a-date', 'done' => '<p>x</p>']],
        todos: [['title' => '']],
    ))->assertUnprocessable()->assertJsonValidationErrors(['updates.0.date', 'todos.0.title']);
});

test('finishing gives a one-time sign-in link for the Mac app\'s window', function () {
    $user = User::factory()->cloudPlan()->create();
    $connection = approvedConnection($user);

    $loginUrl = $this->postJson("/api/desktop/connections/{$connection->code}/finish", ['secret' => SECRET])
        ->assertOk()
        ->json('login_url');

    $this->get($loginUrl)->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);

    auth()->logout();
    $this->get($loginUrl)->assertForbidden();
    $this->assertGuest();

    // Nothing more can be uploaded once it's finished.
    $this->postJson("/api/desktop/connections/{$connection->code}/import", importPayload())->assertStatus(409);
});

test('the sign-in link has to be signed and unexpired', function () {
    $connection = approvedConnection(User::factory()->cloudPlan()->create());
    $connection->forceFill(['finished_at' => now()])->save();

    $this->get(route('desktop.login', $connection->code))->assertForbidden();

    $expired = URL::temporarySignedRoute('desktop.login', now()->subMinute(), ['connection' => $connection->code]);
    $this->get($expired)->assertForbidden();
    $this->assertGuest();
});

// --- The Mac app's side ----------------------------------------------------

beforeEach(function () {
    config(['services.cloud.url' => 'https://cloud.test']);
});

/**
 * Sign in as the Mac app's local user, the way the desktop app does.
 */
function onTheMac(): User
{
    config(['nativephp-internal.running' => true]);
    $user = User::factory()->create();
    test()->actingAs($user);

    return $user;
}

test('Move to cloud opens the approval page in the browser', function () {
    onTheMac();
    Shell::fake();
    Http::fake(['cloud.test/api/desktop/connections' => Http::response(['code' => 'abc', 'approve_url' => 'https://cloud.test/desktop/connect/abc', 'expires_at' => now()->toIso8601String()], 201)]);

    Livewire::test(Plan::class)
        ->call('moveToCloud')
        ->assertSet('moveStatus', 'waiting')
        ->assertSeeHtml('data-test="move-waiting"')
        ->assertDontSee(session('move_to_cloud.secret'));

    Shell::assertOpenedExternal('https://cloud.test/desktop/connect/abc');
    Http::assertSent(fn ($request) => strlen($request['secret']) === 64);
    expect(session('move_to_cloud.code'))->toBe('abc');
});

test('Move to cloud explains when the cloud can\'t be reached', function () {
    onTheMac();
    Shell::fake();
    Http::fake(['cloud.test/*' => Http::failedConnection()]);

    Livewire::test(Plan::class)
        ->call('moveToCloud')
        ->assertSeeHtml('data-test="move-error"')
        ->assertSee('Couldn\'t reach cloud.test');
});

test('once approved, the Mac uploads everything and opens the cloud', function () {
    $user = onTheMac();
    DailyUpdate::factory()->for($user)->create(['date' => '2026-10-01', 'done' => '<p>Mac log</p>']);
    $user->todos()->create(['title' => 'Mac todo']);
    session(['move_to_cloud' => ['code' => 'abc', 'secret' => SECRET, 'approve_url' => 'https://cloud.test/desktop/connect/abc']]);

    Http::fake([
        'cloud.test/api/desktop/connections/abc/status' => Http::response(['status' => 'approved']),
        'cloud.test/api/desktop/connections/abc/import' => Http::response(['updates' => 1, 'todos' => 1]),
        'cloud.test/api/desktop/connections/abc/finish' => Http::response(['login_url' => 'https://cloud.test/desktop/login/abc?signature=x']),
    ]);
    $this->mock(CloudMode::class)->shouldReceive('enable')->once()->with('https://cloud.test');

    Livewire::test(Plan::class)
        ->set('moveStatus', 'waiting')
        ->call('checkMoveToCloud')
        ->assertRedirect('https://cloud.test/desktop/login/abc?signature=x');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/import')
        && $request['secret'] === SECRET
        && collect($request['updates'])->contains('done', '<p>Mac log</p>'));
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/import')
        && collect($request['todos'])->contains('title', 'Mac todo'));
    expect(session('move_to_cloud'))->toBeNull();
});

test('while waiting, nothing happens until it\'s approved', function () {
    onTheMac();
    session(['move_to_cloud' => ['code' => 'abc', 'secret' => SECRET, 'approve_url' => 'https://cloud.test/desktop/connect/abc']]);
    Http::fake(['cloud.test/api/desktop/connections/abc/status' => Http::response(['status' => 'pending'])]);
    $this->mock(CloudMode::class)->shouldNotReceive('enable');

    Livewire::test(Plan::class)
        ->set('moveStatus', 'waiting')
        ->call('checkMoveToCloud')
        ->assertSet('moveStatus', 'waiting')
        ->assertNoRedirect();

    Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/import'));
});

test('a refused upload leaves the Mac as it was and says why', function () {
    $user = onTheMac();
    DailyUpdate::factory()->for($user)->create(['date' => '2026-10-01', 'done' => '<p>Mac log</p>']);
    session(['move_to_cloud' => ['code' => 'abc', 'secret' => SECRET, 'approve_url' => 'https://cloud.test/desktop/connect/abc']]);
    Http::fake([
        'cloud.test/api/desktop/connections/abc/status' => Http::response(['status' => 'approved']),
        'cloud.test/api/desktop/connections/abc/import' => Http::response(['message' => 'This account no longer has the cloud plan.'], 402),
    ]);
    $this->mock(CloudMode::class)->shouldNotReceive('enable');

    Livewire::test(Plan::class)
        ->set('moveStatus', 'waiting')
        ->call('checkMoveToCloud')
        ->assertSet('moveStatus', 'This account no longer has the cloud plan.')
        ->assertNoRedirect();

    expect(session('move_to_cloud'))->toBeNull();
});

test('an expired approval link starts over', function () {
    onTheMac();
    session(['move_to_cloud' => ['code' => 'abc', 'secret' => SECRET, 'approve_url' => 'https://cloud.test/desktop/connect/abc']]);
    Http::fake(['cloud.test/api/desktop/connections/abc/status' => Http::response(['status' => 'expired'])]);

    Livewire::test(Plan::class)
        ->set('moveStatus', 'waiting')
        ->call('checkMoveToCloud')
        ->assertSeeHtml('data-test="move-error"')
        ->assertSee('The approval link expired');
});

test('the Cloud menu goes back to this Mac\'s data', function () {
    $this->mock(CloudMode::class)->shouldReceive('disable')->once();
    NativeApp::shouldReceive('relaunch')->once();

    event(new UseLocalDataRequested);
});

test('the full move works from start to finish against the hosted side', function () {
    // The Mac app's client, pointed at this same app, run against the real API.
    $cloudUser = User::factory()->cloudPlan()->create();
    DailyUpdate::factory()->for($cloudUser)->create(['date' => '2026-10-05', 'done' => '<p>Web notes</p>']);

    $macUser = User::factory()->create();
    DailyUpdate::factory()->for($macUser)->create(['date' => '2026-10-05', 'done' => '<p>Mac notes</p>']);
    DailyUpdate::factory()->for($macUser)->create(['date' => '2026-10-06', 'done' => '<p>Only on the Mac</p>']);

    Http::fake(function ($request) {
        $path = parse_url($request->url(), PHP_URL_PATH);
        $response = $this->postJson($path, $request->data());

        return Http::response($response->json(), $response->status());
    });

    $client = app(CloudClient::class);
    $started = $client->start();

    $this->actingAs($cloudUser);
    Livewire::test(Connect::class, ['code' => $started['code']])->call('approve');

    expect($client->status($started['code'], $started['secret']))->toBe('approved')
        ->and($client->upload($macUser, $started['code'], $started['secret']))->toBe(['updates' => 2, 'todos' => 0])
        ->and($client->finish($started['code'], $started['secret']))->toStartWith(route('desktop.login', $started['code']));

    expect($cloudUser->dailyUpdates()->count())->toBe(2)
        ->and($cloudUser->dailyUpdates()->whereDate('date', '2026-10-05')->sole()->done)->toContain('Web notes')->toContain('Mac notes');
});
