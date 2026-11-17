<?php

use App\Livewire\Settings\Profile;
use App\Models\User;
use App\Support\Desktop;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

test('today follows the user\'s own timezone', function () {
    // 8 PM in UTC on November 17 is already the morning of November 18 in Auckland.
    $this->travelTo(Carbon::parse('2026-11-17 20:00:00', 'UTC'));

    $this->actingAs(User::factory()->create(['timezone' => 'Pacific/Auckland']))
        ->get(route('dashboard'))
        ->assertSee(route('updates.show', '2026-11-18'));

    $this->actingAs(User::factory()->create(['timezone' => 'America/Phoenix']))
        ->get(route('dashboard'))
        ->assertSee(route('updates.show', '2026-11-17'))
        ->assertDontSee(route('updates.show', '2026-11-18'));
});

test('users without a timezone use the default one', function () {
    config(['app.default_timezone' => 'Pacific/Auckland']);

    expect(User::factory()->make(['timezone' => null])->effectiveTimezone())->toBe('Pacific/Auckland');
});

test('registration keeps the browser\'s timezone', function (string $sent, ?string $stored) {
    $this->post(route('register.store'), [
        'name' => 'Jordan Doe',
        'email' => 'jordan@example.com',
        'timezone' => $sent,
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasNoErrors();

    expect(User::sole()->timezone)->toBe($stored);
})->with([
    'a real timezone' => ['America/New_York', 'America/New_York'],
    'not a timezone' => ['Mars/Olympus_Mons', null],
    'nothing sent' => ['', null],
]);

test('the timezone can be changed in profile settings', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Profile::class)
        ->set('timezone', 'Europe/Amsterdam')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($user->fresh()->timezone)->toBe('Europe/Amsterdam');

    Livewire::test(Profile::class)
        ->assertSet('timezone', 'Europe/Amsterdam')
        ->set('timezone', '')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($user->fresh()->timezone)->toBeNull();

    Livewire::test(Profile::class)
        ->set('timezone', 'Mars/Olympus_Mons')
        ->call('updateProfileInformation')
        ->assertHasErrors('timezone');
});

test('the Mac\'s timezone is read from its zoneinfo path', function (string $path, ?string $timezone) {
    expect(Desktop::timezoneFromZoneinfoPath($path))->toBe($timezone);
})->with([
    'macOS' => ['/var/db/timezone/zoneinfo/America/Phoenix', 'America/Phoenix'],
    'Linux' => ['/usr/share/zoneinfo/Europe/Amsterdam', 'Europe/Amsterdam'],
    'not a timezone' => ['/usr/share/zoneinfo/Not/A_Place', null],
    'no zoneinfo' => ['/etc/somewhere-else', null],
]);
