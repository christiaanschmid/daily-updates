<?php

namespace App\Livewire\Updates;

use App\Events\CloudSynced;
use App\Models\DailyUpdate;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * @property-read DailyUpdate|null $update
 */
#[Title('Huddle')]
class Huddle extends Component
{
    /**
     * The update to share at this morning's huddle: the log of the most recent
     * workday before today.
     */
    #[Computed]
    public function update(): ?DailyUpdate
    {
        return Auth::user()->visibleUpdates()
            ->whereDate('date', '<', today())
            ->latest('date')
            ->first();
    }

    /**
     * Mac app: show what a sync brought in from the cloud.
     */
    #[On('native:'.CloudSynced::class)]
    public function refreshFromCloud(): void
    {
        // Re-renders with the latest logs.
    }

    public function render(): View
    {
        return view('livewire.updates.huddle');
    }
}
