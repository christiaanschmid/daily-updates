<?php

use App\Livewire\Settings\Profile;
use App\Models\User;
use Livewire\Livewire;
use Native\Desktop\Facades\Shell;

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

test('the window has a Mac toolbar instead of the website header', function () {
    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-desktop', false)
        ->assertSee('app-drag', false)
        ->assertDontSee('data-test="sidebar-menu-button"', false);

    config(['nativephp-internal.running' => false]);
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('data-desktop', false)
        ->assertSee('data-test="sidebar-menu-button"', false);
});

test('links to other sites open in the default browser', function () {
    Shell::fake();
    $this->get(route('dashboard'));

    $this->postJson(route('desktop.open-external'), ['url' => 'https://example.com/page'])->assertNoContent();
    $this->postJson(route('desktop.open-external'), ['url' => 'mailto:someone@example.com'])->assertNoContent();

    Shell::assertOpenedExternal('https://example.com/page');
    Shell::assertOpenedExternal('mailto:someone@example.com');
});

test('only web and email links can be opened', function () {
    Shell::fake();
    $this->get(route('dashboard'));

    $this->postJson(route('desktop.open-external'), ['url' => 'javascript:alert(1)'])->assertUnprocessable();
    $this->postJson(route('desktop.open-external'), ['url' => 'file:///etc/passwd'])->assertUnprocessable();
});

test('the browser version can\'t open links on the server', function () {
    config(['nativephp-internal.running' => false]);
    $this->actingAs(User::factory()->create());

    $this->postJson(route('desktop.open-external'), ['url' => 'https://example.com'])->assertNotFound();
});

test('the Go menu\'s Today opens today\'s page', function () {
    $this->get(route('today'))->assertRedirect(route('updates.show', today()->format('Y-m-d')));
});
