<?php

use App\Models\User;

test('the home page sends signed in users to their updates', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('home'))->assertRedirect(route('dashboard'));
});

test('the home page sends guests on to the login page', function () {
    $this->followingRedirects()
        ->get(route('home'))
        ->assertOk()
        ->assertSee('Log in to your account');
});
