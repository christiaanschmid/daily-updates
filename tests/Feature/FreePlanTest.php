<?php

use App\Livewire\Updates\Huddle;
use App\Livewire\Updates\Index;
use App\Livewire\Updates\Show;
use App\Models\DailyUpdate;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    // Today is Tuesday, November 17, so the free plan's 14 days start on Wednesday, November 4.
    $this->travelTo(now()->setDate(2026, 11, 17)->setTime(12, 0));
});

/**
 * A user with one log on the first visible day and two older ones.
 */
function userWithOldAndRecentLogs(User $user): User
{
    DailyUpdate::factory()->for($user)->create(['date' => '2026-11-04', 'done' => '<p>Recent work</p>']);
    DailyUpdate::factory()->for($user)->create(['date' => '2026-11-03', 'done' => '<p>Older work</p>']);
    DailyUpdate::factory()->for($user)->create(['date' => '2026-10-01', 'done' => '<p>Much older work</p>']);

    return $user;
}

test('a free web account sees only its last 14 days of history', function () {
    $this->actingAs(userWithOldAndRecentLogs(User::factory()->create()));

    Livewire::test(Index::class)
        ->assertSee('Recent work')
        ->assertDontSee('Older work')
        ->assertDontSee('Much older work')
        ->assertSee('2 older logs are kept');
});

test('search on a free web account only finds recent logs', function () {
    $this->actingAs(userWithOldAndRecentLogs(User::factory()->create()));

    Livewire::test(Index::class)
        ->set('search', 'work')
        ->assertSee('Recent work')
        ->assertDontSee('Older work');
});

test('an older log shows a notice instead of its content and cannot be changed', function () {
    $user = userWithOldAndRecentLogs(User::factory()->create());
    $this->actingAs($user);

    Livewire::test(Show::class, ['date' => '2026-11-03'])
        ->assertSet('editing', false)
        ->assertSeeHtml('data-test="history-limit-notice"')
        ->assertSee('Your log for this day is kept')
        ->assertDontSee('Older work')
        ->call('edit')
        ->assertForbidden();

    Livewire::test(Show::class, ['date' => '2026-11-03'])->call('delete')->assertForbidden();

    expect($user->dailyUpdates()->count())->toBe(3);
});

test('a free web account cannot start a log for an older day', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(Show::class, ['date' => '2026-10-15'])
        ->assertSet('editing', false)
        ->assertSee('Upgrade to the cloud plan to log any day')
        ->call('save')
        ->assertForbidden();
});

test('a new log is not moved to a day outside the 14 days', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Show::class, ['date' => '2026-11-17'])
        ->set('done', '<p>Started today</p>')
        ->set('selectedDate', '2026-10-15')
        ->assertRedirect(route('updates.show', '2026-10-15'));

    expect($user->dailyUpdates()->sole()->date->format('Y-m-d'))->toBe('2026-11-17');
});

test('moving between logs stops at the 14 days', function () {
    $this->actingAs(userWithOldAndRecentLogs(User::factory()->create()));

    Livewire::test(Show::class, ['date' => '2026-11-04'])
        ->assertSee('Recent work')
        ->assertDontSeeHtml('aria-label="Previous update"');
});

test('the huddle skips logs older than 14 days', function () {
    $user = User::factory()->create();
    DailyUpdate::factory()->for($user)->create(['date' => '2026-10-01', 'done' => '<p>Much older work</p>']);
    $this->actingAs($user);

    Livewire::test(Huddle::class)->assertDontSee('Much older work');
});

test('the cloud plan shows the full history', function () {
    $this->actingAs(userWithOldAndRecentLogs(User::factory()->cloudPlan()->create()));

    Livewire::test(Index::class)
        ->assertSee('Recent work')
        ->assertSee('Older work')
        ->assertSee('Much older work')
        ->assertDontSeeHtml('data-test="history-limit-notice"');

    Livewire::test(Show::class, ['date' => '2026-10-01'])->assertSee('Much older work');
});

test('the desktop app shows the full history without the cloud plan', function () {
    config(['nativephp-internal.running' => true]);
    $this->actingAs(userWithOldAndRecentLogs(User::factory()->create()));

    Livewire::test(Index::class)->assertSee('Much older work');
    Livewire::test(Show::class, ['date' => '2026-10-01'])->assertSee('Much older work');
});

test('the cloud plan can be granted and taken away by email', function () {
    $user = User::factory()->create(['email' => 'friend@example.com']);

    $this->artisan('cloud-plan:grant', ['email' => 'friend@example.com'])->assertSuccessful();
    expect($user->fresh()->hasCloudPlan())->toBeTrue();

    $this->artisan('cloud-plan:grant', ['email' => 'friend@example.com', '--revoke' => true])->assertSuccessful();
    expect($user->fresh()->hasCloudPlan())->toBeFalse();

    $this->artisan('cloud-plan:grant', ['email' => 'nobody@example.com'])->assertFailed();
});
