<?php

use App\Models\User;

test('the home page sends signed in users to their updates', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('home'))->assertRedirect(route('dashboard'));
});

test('the home page shows guests on the web the landing page', function () {
    config(['nativephp-internal.running' => false]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Know what you did yesterday.')
        ->assertSee(route('login'))
        ->assertSee(route('register'));
});

test('the Mac app skips the landing page', function () {
    config(['nativephp-internal.running' => true]);

    $this->get(route('home'))->assertRedirect(route('dashboard'));
});
