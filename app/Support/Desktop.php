<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

class Desktop
{
    /**
     * Whether the app is running as the NativePHP desktop app.
     */
    public static function running(): bool
    {
        return (bool) config('nativephp-internal.running');
    }

    /**
     * The single local user of the desktop app, created on first launch.
     *
     * The desktop app has one person using it, so there is no login: the
     * oldest account is used, and its email counts as verified.
     */
    public static function user(): User
    {
        $user = User::query()->oldest('id')->first();

        if ($user === null) {
            $user = User::create([
                'name' => Str::title(get_current_user()) ?: 'Me',
                'email' => 'desktop@localhost',
                'password' => Str::random(64),
            ]);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return $user;
    }
}
