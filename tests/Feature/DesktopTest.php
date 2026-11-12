<?php

use App\Livewire\Settings\Profile;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    config(['nativephp-internal.running' => true]);
});

test('a local user is created and signed in on first launch', function () {
    $this->get(route('dashboard'))->assertOk();

    expect(User::count())->toBe(1);

    $user = User::sole();
    expect($user->hasVerifiedEmail())->toBeTrue();
    $this->assertAuthenticatedAs($user);
});

test('the existing account is used and verified instead of creating another', function () {
    $user = User::factory()->unverified()->create();
    User::factory()->create();

    $this->get(route('dashboard'))->assertOk();

    expect(User::count())->toBe(2);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $this->assertAuthenticatedAs($user);
});

test('the login and register pages redirect into the app', function (string $route) {
    $this->get(route($route))->assertRedirect(route('dashboard'));
})->with(['login', 'register']);

test('the security settings page does not exist', function () {
    $this->get(route('security.edit'))->assertNotFound();
});

test('login-only controls are hidden', function () {
    $user = User::factory()->create();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('data-test="logout-button"', escape: false)
        ->assertDontSee($user->email);

    $this->get(route('profile.edit'))
        ->assertOk()
        ->assertDontSee(route('security.edit'))
        ->assertDontSee('wire:model="email"', escape: false);

    Livewire::test(Profile::class)->assertDontSeeLivewire('settings.delete-user-form');
});

test('the browser version still requires a login', function () {
    config(['nativephp-internal.running' => false]);

    $this->get(route('dashboard'))->assertRedirect(route('login'));

    expect(User::count())->toBe(0);
});
