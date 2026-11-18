<?php

namespace App\Livewire\Desktop;

use App\Events\CloudSynced;
use App\Models\CloudLink;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Mac app: the sync icon in the toolbar. Shows whether the last sync worked, and
 * opens the sync settings.
 */
class SyncStatus extends Component
{
    #[On('native:'.CloudSynced::class)]
    public function refreshStatus(): void
    {
        // Re-renders with the latest sync.
    }

    public function render(): View
    {
        return view('livewire.desktop.sync-status', ['link' => CloudLink::current()]);
    }
}
