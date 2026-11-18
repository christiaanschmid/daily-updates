<?php

namespace App\Events;

/**
 * Mac app: a sync brought in changes from the cloud. NativePHP passes events on its
 * "nativephp" channel to the window, where Livewire components hear it as
 * "native:App\Events\CloudSynced" and show the new data.
 */
class CloudSynced
{
    /**
     * @return list<string>
     */
    public function broadcastOn(): array
    {
        return ['nativephp'];
    }
}
