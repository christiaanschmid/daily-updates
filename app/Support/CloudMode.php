<?php

namespace App\Support;

use Native\Desktop\Facades\Settings;

/**
 * Whether the Mac app shows the cloud account instead of this Mac's own logs.
 * Kept in NativePHP's settings, which stay on the Mac across app updates.
 */
class CloudMode
{
    protected const KEY = 'cloud_url';

    /**
     * The cloud address the app opens instead of its local logs, or null when it uses this Mac's data.
     */
    public function url(): ?string
    {
        $url = Settings::get(self::KEY);

        return is_string($url) && $url !== '' ? $url : null;
    }

    public function enable(string $url): void
    {
        Settings::set(self::KEY, $url);
    }

    public function disable(): void
    {
        Settings::forget(self::KEY);
    }
}
