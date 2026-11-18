<?php

namespace App\Events;

use Native\Desktop\Events\Contracts\DispatchableFromNative;

/**
 * Fired by the Mac app's File > Sync Now menu item.
 */
class SyncNowRequested implements DispatchableFromNative
{
    /**
     * NativePHP passes the clicked menu item and any key combination.
     *
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $combo
     */
    public function __construct(public array $item = [], public array $combo = []) {}
}
