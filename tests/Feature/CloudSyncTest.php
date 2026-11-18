<?php

use App\Events\SyncNowRequested;
use App\Jobs\SyncWithCloud;
use App\Livewire\Desktop\Connect;
use App\Livewire\Settings\Plan;
use App\Models\CloudLink;
use App\Models\DailyUpdate;
use App\Models\DesktopConnection;
use App\Models\SyncTombstone;
use App\Models\Todo;
use App\Models\User;
use App\Sync\CloudSync;
use App\Sync\ServerSync;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Native\Desktop\Facades\Shell;

const SECRET = 'mac-secret-0123456789abcdefghijklmnopqrstuvwxyz0123456789ABCD';

/**
 * A connection the given user approved, ready to finish.
 */
function approvedConnection(User $user): DesktopConnection
{
    $connection = DesktopConnection::start(SECRET);
    $connection->approveFor($user);

    return $connection->fresh();
}

/**
 * A finished connection's sync token for the given user.
 */
function syncToken(User $user): string
{
    return approvedConnection($user)->issueToken();
}

/**
 * The Mac app's sync request, as the cloud receives it.
 */
function syncAs(string $token, array $payload = []): TestResponse
{
    return test()->postJson('/api/desktop/sync', [
        'cursor' => 0,
        'updates' => [],
        'todos' => [],
        ...$payload,
    ], ['Authorization' => 'Bearer '.$token]);
}

// --- Connecting: the hosted side ------------------------------------------

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


// --- Sync: the hosted side -------------------------------------------------

test('finishing gives the Mac a token to sync with, once', function () {
    $user = User::factory()->cloudPlan()->create();
    $connection = approvedConnection($user);

    $token = $this->postJson("/api/desktop/connections/{$connection->code}/finish", ['secret' => SECRET])
        ->assertOk()
        ->json('token');

    expect($token)->toHaveLength(64)
        ->and($connection->fresh()->token_hash)->not->toBe($token);

    syncAs($token)->assertOk();

    $this->postJson("/api/desktop/connections/{$connection->code}/finish", ['secret' => SECRET])->assertStatus(409);
});

test('a connection can\'t finish before approval, after expiry or without the plan', function () {
    $user = User::factory()->cloudPlan()->create();
    $pending = DesktopConnection::start(SECRET);
    $this->postJson("/api/desktop/connections/{$pending->code}/finish", ['secret' => SECRET])->assertStatus(409);

    $connection = approvedConnection($user);
    $user->forceFill(['cloud_plan_granted' => false])->save();
    $this->postJson("/api/desktop/connections/{$connection->code}/finish", ['secret' => SECRET])->assertStatus(402);

    $user->forceFill(['cloud_plan_granted' => true])->save();
    $this->travel(DesktopConnection::UPLOAD_MINUTES + 1)->minutes();
    $this->postJson("/api/desktop/connections/{$connection->code}/finish", ['secret' => SECRET])->assertStatus(410);
});

test('syncing needs a token that is still connected, and the cloud plan', function () {
    $user = User::factory()->cloudPlan()->create();
    $token = syncToken($user);

    syncAs('not-the-token')->assertUnauthorized();
    approvedConnection($user); // An unfinished connection has no token yet.
    syncAs('')->assertUnauthorized();

    $user->forceFill(['cloud_plan_granted' => false])->save();
    syncAs($token)->assertStatus(402);

    $user->forceFill(['cloud_plan_granted' => true])->save();
    $this->deleteJson('/api/desktop/sync', [], ['Authorization' => 'Bearer '.$token])->assertOk();
    syncAs($token)->assertUnauthorized();
});

test('the first sync sends everything in the account, and later ones only what changed', function () {
    $user = User::factory()->cloudPlan()->create();
    $log = DailyUpdate::factory()->for($user)->create(['date' => '2026-10-01', 'done' => '<p>From the web</p>', 'up_next' => null]);
    $todo = $user->todos()->create(['title' => 'Web todo']);
    User::factory()->cloudPlan()->create()->todos()->create(['title' => 'Someone else\'s']);
    $token = syncToken($user);

    $first = syncAs($token)->assertOk();

    expect($first->json('updates'))->toBe([[
        'uuid' => $log->uuid, 'date' => '2026-10-01', 'done' => '<p>From the web</p>', 'up_next' => null, 'version' => $log->fresh()->sync_version,
    ]])
        ->and($first->json('todos'))->toHaveCount(1)
        ->and($first->json('todos.0.uuid'))->toBe($todo->uuid)
        ->and($first->json('has_more'))->toBeFalse();

    $cursor = $first->json('cursor');
    expect(syncAs($token, ['cursor' => $cursor])->json())->toMatchArray(['updates' => [], 'todos' => [], 'deleted' => [], 'cursor' => $cursor]);

    $log->update(['done' => '<p>Edited on the web</p>']);
    $todo->delete();

    $next = syncAs($token, ['cursor' => $cursor]);
    expect($next->json('updates.0.done'))->toBe('<p>Edited on the web</p>')
        ->and($next->json('deleted'))->toBe([['type' => 'todo', 'uuid' => $todo->uuid, 'version' => $next->json('cursor')]])
        ->and($next->json('cursor'))->toBeGreaterThan($cursor);
});

test('changes are sent a page at a time', function () {
    $user = User::factory()->cloudPlan()->create();
    $token = syncToken($user);

    foreach (range(1, ServerSync::PAGE_SIZE + 5) as $i) {
        $user->todos()->create(['title' => "Todo {$i}"]);
    }

    $first = syncAs($token);
    expect($first->json('todos'))->toHaveCount(ServerSync::PAGE_SIZE)
        ->and($first->json('has_more'))->toBeTrue();

    $second = syncAs($token, ['cursor' => $first->json('cursor')]);
    expect($second->json('todos'))->toHaveCount(5)
        ->and($second->json('has_more'))->toBeFalse();
});

test('new logs and todos from the Mac are added with the Mac\'s ids', function () {
    $user = User::factory()->cloudPlan()->create();
    $token = syncToken($user);
    [$logId, $todoId, $doneId] = [(string) Str::uuid(), (string) Str::uuid(), (string) Str::uuid()];

    $response = syncAs($token, [
        'updates' => [
            ['uuid' => $logId, 'date' => '2026-10-01', 'done' => '<p>Safe</p><script>alert(1)</script>', 'up_next' => null],
        ],
        'todos' => [
            ['uuid' => $todoId, 'title' => 'Open todo', 'completed_at' => null, 'created_at' => '2026-10-01 09:00:00'],
            ['uuid' => $doneId, 'title' => 'Done todo', 'completed_at' => '2026-10-01 17:30:00', 'created_at' => '2026-09-30 09:00:00'],
        ],
    ])->assertOk();

    $log = $user->dailyUpdates()->sole();
    expect($log->uuid)->toBe($logId)
        ->and($log->done)->toContain('Safe')->not->toContain('<script>')
        ->and($user->todos()->where('uuid', $doneId)->sole()->completed_at->format('Y-m-d H:i'))->toBe('2026-10-01 17:30')
        ->and($user->todos()->where('uuid', $todoId)->sole()->created_at->format('Y-m-d H:i'))->toBe('2026-10-01 09:00')
        ->and(collect($response->json('updates'))->pluck('uuid')->all())->toBe([$logId]);
});

test('a day logged in both places keeps the cloud\'s notes with the Mac\'s added below', function () {
    $user = User::factory()->cloudPlan()->create();
    $webLog = DailyUpdate::factory()->for($user)->create(['date' => '2026-10-05', 'done' => '<p>Written on the web</p>']);
    $token = syncToken($user);
    $macId = (string) Str::uuid();
    $payload = ['updates' => [['uuid' => $macId, 'date' => '2026-10-05', 'done' => '<p>Written on the Mac</p>']]];

    $response = syncAs($token, $payload);

    $combined = $webLog->fresh()->done;
    expect($combined)->toContain('Written on the web')->toContain('Written on the Mac')
        ->and(strpos($combined, 'web'))->toBeLessThan(strpos($combined, 'Mac'))
        ->and($user->dailyUpdates()->count())->toBe(1)
        // The Mac drops its own copy and takes the combined one.
        ->and($response->json('deleted'))->toContain(['type' => 'daily_update', 'uuid' => $macId, 'version' => SyncTombstone::query()->where('uuid', $macId)->value('sync_version')])
        ->and(collect($response->json('updates'))->pluck('uuid')->all())->toBe([$webLog->uuid]);

    // Sent again, such as after a dropped response, nothing is added twice.
    syncAs($token, $payload);
    expect(substr_count($webLog->fresh()->done, 'Written on the Mac'))->toBe(1);
});

test('a log changed on both sides since the Mac last synced keeps both', function () {
    $user = User::factory()->cloudPlan()->create();
    $log = DailyUpdate::factory()->for($user)->create(['date' => '2026-10-05', 'done' => '<p>First</p>']);
    $token = syncToken($user);
    $base = $log->fresh()->sync_version;

    $log->update(['done' => '<p>First</p><p>Added on the web</p>']);

    $response = syncAs($token, ['cursor' => $base, 'updates' => [
        ['uuid' => $log->uuid, 'base_version' => $base, 'date' => '2026-10-05', 'done' => '<p>First</p><p>Added on the Mac</p>'],
    ]]);

    expect($log->fresh()->done)->toContain('Added on the web')->toContain('Added on the Mac')
        ->and($response->json('updates.0.done'))->toContain('Added on the Mac');
});

test('a log only changed on the Mac takes the Mac\'s version, including a new day', function () {
    $user = User::factory()->cloudPlan()->create();
    $log = DailyUpdate::factory()->for($user)->create(['date' => '2026-10-05', 'done' => '<p>Old</p>']);
    $token = syncToken($user);
    $base = $log->fresh()->sync_version;

    syncAs($token, ['cursor' => $base, 'updates' => [
        ['uuid' => $log->uuid, 'base_version' => $base, 'date' => '2026-10-06', 'done' => '<p>New</p>'],
    ]]);

    expect($log->fresh())->done->toBe('<p>New</p>')
        ->and($log->fresh()->date->format('Y-m-d'))->toBe('2026-10-06');
});

test('an edit beats a delete', function () {
    $user = User::factory()->cloudPlan()->create();
    $log = DailyUpdate::factory()->for($user)->create(['date' => '2026-10-05', 'done' => '<p>Kept</p>']);
    $todo = $user->todos()->create(['title' => 'Renamed on the web']);
    $token = syncToken($user);
    $logBase = $log->fresh()->sync_version;
    $todoBase = $todo->fresh()->sync_version;

    // Edited on the web after the Mac's copy, then deleted on the Mac: it stays.
    $log->update(['done' => '<p>Kept, and edited</p>']);
    syncAs($token, ['updates' => [['uuid' => $log->uuid, 'base_version' => $logBase, 'deleted' => true]]])->assertOk();
    expect($log->fresh())->not->toBeNull();

    // Not edited since: the delete goes through.
    syncAs($token, ['todos' => [['uuid' => $todo->uuid, 'base_version' => $todoBase, 'deleted' => true]]])->assertOk();
    expect($todo->fresh())->toBeNull();

    // Deleted on the web, then edited on the Mac: it comes back.
    syncAs($token, ['todos' => [['uuid' => $todo->uuid, 'base_version' => $todoBase, 'title' => 'Still needed']]])->assertOk();
    expect($user->todos()->where('uuid', $todo->uuid)->sole()->title)->toBe('Still needed');
});

test('a todo changed on both sides keeps the cloud\'s title and stays checked off', function () {
    $user = User::factory()->cloudPlan()->create();
    $todo = $user->todos()->create(['title' => 'Original']);
    $token = syncToken($user);
    $base = $todo->fresh()->sync_version;

    $todo->update(['title' => 'Renamed on the web']);

    syncAs($token, ['todos' => [
        ['uuid' => $todo->uuid, 'base_version' => $base, 'title' => 'Renamed on the Mac', 'completed_at' => '2026-10-05 16:00:00'],
    ]]);

    expect($todo->fresh())
        ->title->toBe('Renamed on the web')
        ->and($todo->fresh()->completed_at->format('Y-m-d H:i'))->toBe('2026-10-05 16:00');
});

test('on the first sync, todos already in the cloud aren\'t added twice', function () {
    $user = User::factory()->cloudPlan()->create();
    $user->todos()->create(['title' => 'Already here']);
    $token = syncToken($user);
    $todo = fn (string $title) => ['uuid' => (string) Str::uuid(), 'title' => $title, 'completed_at' => null];

    syncAs($token, ['initial' => true, 'todos' => [$todo('Already here'), $todo('New one')]]);
    expect($user->todos()->count())->toBe(2);

    // Later, a second todo with the same title is just another todo.
    syncAs($token, ['todos' => [$todo('Already here')]]);
    expect($user->todos()->count())->toBe(3);
});

test('what the Mac sends is checked like anything typed in', function () {
    $token = syncToken(User::factory()->cloudPlan()->create());

    syncAs($token, [
        'updates' => [['uuid' => 'not-a-uuid', 'date' => 'not-a-date', 'done' => '<p>x</p>']],
        'todos' => [['uuid' => (string) Str::uuid(), 'title' => '']],
    ])->assertUnprocessable()->assertJsonValidationErrors(['updates.0.uuid', 'updates.0.date', 'todos.0.title']);
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

/**
 * Send the Mac app's calls to the cloud to this same app, as the hosted side.
 * While $offline() is true, the cloud can't be reached.
 */
function cloudIsThisApp(?Closure $offline = null): void
{
    Http::fake(function ($request) use ($offline) {
        if ($offline !== null && $offline()) {
            throw new ConnectionException('Offline');
        }

        config(['nativephp-internal.running' => false]);

        try {
            $response = test()->json(
                $request->method(),
                parse_url($request->url(), PHP_URL_PATH),
                $request->data(),
                ['Authorization' => $request->header('Authorization')[0] ?? ''],
            );

            return Http::response($response->json(), $response->status());
        } finally {
            config(['nativephp-internal.running' => true]);
        }
    });
}

/**
 * A Mac connected to the given cloud account, with nothing synced yet.
 */
function connectedTo(User $cloudUser): CloudSync
{
    $sync = app(CloudSync::class);
    $sync->connect('https://cloud.test', syncToken($cloudUser));

    return $sync;
}

/**
 * A log's days and notes, to compare the Mac and the cloud.
 *
 * @return array<string, string|null>
 */
function logsOf(User $user): array
{
    return $user->dailyUpdates()->oldest('date')->get()->mapWithKeys(fn ($log) => [$log->date->format('Y-m-d') => $log->done])->all();
}

test('Turn on sync opens the approval page in the browser', function () {
    onTheMac();
    Shell::fake();
    Http::fake(['cloud.test/api/desktop/connections' => Http::response(['code' => 'abc', 'approve_url' => 'https://cloud.test/desktop/connect/abc', 'expires_at' => now()->toIso8601String()], 201)]);

    Livewire::test(Plan::class)
        ->call('turnOnSync')
        ->assertSet('connectStatus', 'waiting')
        ->assertSeeHtml('data-test="connect-waiting"')
        ->assertDontSee(session('cloud_connect.secret'));

    Shell::assertOpenedExternal('https://cloud.test/desktop/connect/abc');
    Http::assertSent(fn ($request) => strlen($request['secret']) === 64);
    expect(session('cloud_connect.code'))->toBe('abc');
});

test('Turn on sync explains when the cloud can\'t be reached', function () {
    onTheMac();
    Shell::fake();
    Http::fake(['cloud.test/*' => Http::failedConnection()]);

    Livewire::test(Plan::class)
        ->call('turnOnSync')
        ->assertSeeHtml('data-test="connect-error"')
        ->assertSee('Couldn\'t reach cloud.test');
});

test('once approved, the Mac starts syncing, beginning with everything on it', function () {
    $user = onTheMac();
    DailyUpdate::factory()->for($user)->create(['date' => '2026-10-01', 'done' => '<p>Mac log</p>']);
    session(['cloud_connect' => ['code' => 'abc', 'secret' => SECRET, 'approve_url' => 'https://cloud.test/desktop/connect/abc']]);

    Http::fake([
        'cloud.test/api/desktop/connections/abc/status' => Http::response(['status' => 'approved']),
        'cloud.test/api/desktop/connections/abc/finish' => Http::response(['token' => 'the-token']),
        'cloud.test/api/desktop/sync' => Http::response(['cursor' => 7, 'has_more' => false, 'updates' => [], 'todos' => [], 'deleted' => []]),
    ]);

    Livewire::test(Plan::class)
        ->set('connectStatus', 'waiting')
        ->call('checkConnection')
        ->assertNoRedirect()
        ->assertSeeHtml('data-test="sync-on"');

    $link = CloudLink::sole();
    expect($link->token)->toBe('the-token')
        ->and($link->getRawOriginal('token'))->not->toBe('the-token')
        ->and($link->cursor)->toBe(7)
        ->and($link->last_synced_at)->not->toBeNull()
        ->and(session('cloud_connect'))->toBeNull();

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/desktop/sync')
        && $request->header('Authorization')[0] === 'Bearer the-token'
        && $request['initial'] === true
        && collect($request['updates'])->contains('done', '<p>Mac log</p>'));
});

test('while waiting, nothing happens until it\'s approved', function () {
    onTheMac();
    session(['cloud_connect' => ['code' => 'abc', 'secret' => SECRET, 'approve_url' => 'https://cloud.test/desktop/connect/abc']]);
    Http::fake(['cloud.test/api/desktop/connections/abc/status' => Http::response(['status' => 'pending'])]);

    Livewire::test(Plan::class)
        ->set('connectStatus', 'waiting')
        ->call('checkConnection')
        ->assertSet('connectStatus', 'waiting');

    Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/finish'));
    expect(CloudLink::query()->exists())->toBeFalse();
});

test('a refused connection says why', function () {
    onTheMac();
    session(['cloud_connect' => ['code' => 'abc', 'secret' => SECRET, 'approve_url' => 'https://cloud.test/desktop/connect/abc']]);
    Http::fake([
        'cloud.test/api/desktop/connections/abc/status' => Http::response(['status' => 'approved']),
        'cloud.test/api/desktop/connections/abc/finish' => Http::response(['message' => 'This account no longer has the cloud plan.'], 402),
    ]);

    Livewire::test(Plan::class)
        ->set('connectStatus', 'waiting')
        ->call('checkConnection')
        ->assertSet('connectStatus', 'This account no longer has the cloud plan.');

    expect(session('cloud_connect'))->toBeNull()
        ->and(CloudLink::query()->exists())->toBeFalse();
});

test('an expired approval link starts over', function () {
    onTheMac();
    session(['cloud_connect' => ['code' => 'abc', 'secret' => SECRET, 'approve_url' => 'https://cloud.test/desktop/connect/abc']]);
    Http::fake(['cloud.test/api/desktop/connections/abc/status' => Http::response(['status' => 'expired'])]);

    Livewire::test(Plan::class)
        ->set('connectStatus', 'waiting')
        ->call('checkConnection')
        ->assertSeeHtml('data-test="connect-error"')
        ->assertSee('The approval link expired');
});

test('the Mac and the cloud end up with the same logs and todos', function () {
    Queue::fake();
    $mac = onTheMac();
    $cloud = User::factory()->cloudPlan()->create();
    cloudIsThisApp();

    DailyUpdate::factory()->for($mac)->create(['date' => '2026-10-01', 'done' => '<p>Only on the Mac</p>']);
    DailyUpdate::factory()->for($mac)->create(['date' => '2026-10-02', 'done' => '<p>Mac notes</p>']);
    $mac->todos()->create(['title' => 'Mac todo']);
    $mac->todos()->create(['title' => 'On both']);
    config(['nativephp-internal.running' => false]);
    DailyUpdate::factory()->for($cloud)->create(['date' => '2026-10-02', 'done' => '<p>Web notes</p>']);
    DailyUpdate::factory()->for($cloud)->create(['date' => '2026-10-03', 'done' => '<p>Only on the web</p>']);
    $cloud->todos()->create(['title' => 'On both']);
    config(['nativephp-internal.running' => true]);

    $sync = connectedTo($cloud);
    expect($sync->run())->toBeTrue();

    expect(logsOf($mac))->toBe(logsOf($cloud))
        ->and(logsOf($cloud))->toBe([
            '2026-10-01' => '<p>Only on the Mac</p>',
            '2026-10-02' => '<p>Web notes</p><p>Mac notes</p>',
            '2026-10-03' => '<p>Only on the web</p>',
        ])
        ->and($mac->todos()->orderBy('title')->pluck('title')->all())->toBe(['Mac todo', 'On both'])
        ->and($cloud->todos()->orderBy('title')->pluck('title')->all())->toBe(['Mac todo', 'On both'])
        ->and($mac->dailyUpdates()->pluck('uuid')->sort()->values()->all())->toBe($cloud->dailyUpdates()->pluck('uuid')->sort()->values()->all())
        ->and($mac->dailyUpdates()->where('needs_sync', true)->exists())->toBeFalse();
});

test('changes on either side reach the other', function () {
    Queue::fake();
    $mac = onTheMac();
    $cloud = User::factory()->cloudPlan()->create();
    cloudIsThisApp();
    $sync = connectedTo($cloud);

    // Written on the Mac.
    $macLog = DailyUpdate::factory()->for($mac)->create(['date' => '2026-10-01', 'done' => '<p>Mac</p>']);
    $todo = $mac->todos()->create(['title' => 'Call the printer']);
    $sync->run();
    expect(logsOf($cloud))->toBe(['2026-10-01' => '<p>Mac</p>']);

    // Then edited and checked off on the web.
    config(['nativephp-internal.running' => false]);
    $cloud->dailyUpdates()->sole()->update(['done' => '<p>Mac</p><p>Web</p>']);
    $cloud->todos()->sole()->update(['completed_at' => '2026-10-01 15:00:00']);
    config(['nativephp-internal.running' => true]);
    $sync->run();
    expect($macLog->fresh()->done)->toBe('<p>Mac</p><p>Web</p>')
        ->and($todo->fresh()->completed_at->format('H:i'))->toBe('15:00');

    // Then deleted on the Mac.
    $macLog->fresh()->delete();
    $sync->run();
    expect($cloud->dailyUpdates()->exists())->toBeFalse()
        ->and(SyncTombstone::query()->where('needs_sync', true)->exists())->toBeFalse();

    // And deleted on the web.
    config(['nativephp-internal.running' => false]);
    $cloud->todos()->sole()->delete();
    config(['nativephp-internal.running' => true]);
    $sync->run();
    expect($mac->todos()->exists())->toBeFalse();
});

test('a log written on both sides while offline keeps both', function () {
    Queue::fake();
    $mac = onTheMac();
    $cloud = User::factory()->cloudPlan()->create();
    cloudIsThisApp();
    $sync = connectedTo($cloud);

    $log = DailyUpdate::factory()->for($mac)->create(['date' => '2026-10-01', 'done' => '<p>Start</p>']);
    $sync->run();

    $log->fresh()->update(['done' => '<p>Start</p><p>Offline on the Mac</p>']);
    config(['nativephp-internal.running' => false]);
    $cloud->dailyUpdates()->sole()->update(['done' => '<p>Start</p><p>On the web</p>']);
    config(['nativephp-internal.running' => true]);

    $sync->run();

    expect(logsOf($mac))->toBe(logsOf($cloud))
        ->and($log->fresh()->done)->toContain('Offline on the Mac')->toContain('On the web');
});

test('a sync that can\'t reach the cloud tries again later, keeping the Mac\'s changes', function () {
    Queue::fake();
    $mac = onTheMac();
    $cloud = User::factory()->cloudPlan()->create();
    $sync = connectedTo($cloud);
    $log = DailyUpdate::factory()->for($mac)->create(['date' => '2026-10-01', 'done' => '<p>Offline</p>']);

    $offline = true;
    cloudIsThisApp(function () use (&$offline) {
        return $offline;
    });

    expect($sync->run())->toBeFalse()
        ->and(CloudLink::sole()->last_error)->toBe('Couldn\'t reach cloud.test.')
        ->and($log->fresh()->needs_sync)->toBeTrue();

    $offline = false;
    expect($sync->run())->toBeTrue()
        ->and(CloudLink::sole()->last_error)->toBeNull()
        ->and(logsOf($cloud))->toBe(['2026-10-01' => '<p>Offline</p>']);
});

test('a sync refused for the plan says so', function () {
    onTheMac();
    $cloud = User::factory()->cloudPlan()->create();
    $sync = connectedTo($cloud);
    $cloud->forceFill(['cloud_plan_granted' => false])->save();
    cloudIsThisApp();

    expect($sync->run())->toBeFalse()
        ->and(CloudLink::sole()->last_error)->toContain('no longer has the cloud plan');
});

test('a change on the Mac syncs shortly after, once sync is on', function () {
    Queue::fake();
    $mac = onTheMac();

    $mac->todos()->create(['title' => 'Before sync is on']);
    Queue::assertNothingPushed();

    connectedTo(User::factory()->cloudPlan()->create());
    $mac->todos()->create(['title' => 'After']);

    Queue::assertPushed(SyncWithCloud::class);
});

test('Sync Now in the File menu syncs', function () {
    Queue::fake();

    event(new SyncNowRequested);

    Queue::assertPushed(SyncWithCloud::class);
});

test('turning off sync keeps everything on the Mac and disconnects its token', function () {
    $mac = onTheMac();
    $cloud = User::factory()->cloudPlan()->create();
    cloudIsThisApp();
    connectedTo($cloud);
    DailyUpdate::factory()->for($mac)->create(['date' => '2026-10-01']);

    Livewire::test(Plan::class)
        ->assertSeeHtml('data-test="sync-on"')
        ->call('turnOffSync')
        ->assertSeeHtml('data-test="sync-off"');

    expect(CloudLink::query()->exists())->toBeFalse()
        ->and($mac->dailyUpdates()->count())->toBe(1)
        ->and(DesktopConnection::query()->whereNotNull('token_hash')->exists())->toBeFalse();
});

test('the toolbar shows how sync is going', function () {
    onTheMac();

    $this->get(route('dashboard'))->assertOk()->assertDontSee('data-test="sync-status"', false);

    CloudLink::query()->forceCreate(['url' => 'https://cloud.test', 'token' => 'x', 'last_error' => 'Couldn\'t reach cloud.test.']);

    $this->get(route('dashboard'))->assertOk()->assertSee('data-test="sync-status"', false)->assertSee('reach cloud.test');
});
