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
     * The computer's own timezone, such as "America/Phoenix", or null when it can't
     * be read. macOS and Linux point /etc/localtime at a file named after it.
     */
    public static function systemTimezone(): ?string
    {
        $target = @readlink('/etc/localtime');

        return $target === false ? null : self::timezoneFromZoneinfoPath($target);
    }

    /**
     * The timezone name at the end of a zoneinfo path, such as
     * "/var/db/timezone/zoneinfo/America/Phoenix", if it is a real timezone.
     */
    public static function timezoneFromZoneinfoPath(string $path): ?string
    {
        $position = strpos($path, 'zoneinfo/');

        if ($position === false) {
            return null;
        }

        $timezone = substr($path, $position + strlen('zoneinfo/'));

        return in_array($timezone, timezone_identifiers_list(\DateTimeZone::ALL_WITH_BC), true) ? $timezone : null;
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
