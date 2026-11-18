<?php

namespace App\Jobs;

use App\Sync\CloudSync;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Mac app: sync with the cloud, shortly after something changed on this Mac.
 * Changes made in quick succession share one sync.
 */
class SyncWithCloud implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public const DELAY_SECONDS = 3;

    public int $uniqueFor = 30;

    public function handle(CloudSync $sync): void
    {
        $sync->run();
    }
}
