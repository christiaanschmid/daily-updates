<?php

namespace App\Livewire\Updates;

use App\Models\DailyUpdate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * @property-read LengthAwarePaginator<int, DailyUpdate> $updates
 * @property-read bool $hasTodaysUpdate
 */
#[Title('History')]
class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    /**
     * Go back to the first page whenever the search changes.
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * The user's updates, newest first, filtered by the search term.
     *
     * @return LengthAwarePaginator<int, DailyUpdate>
     */
    #[Computed]
    public function updates(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return Auth::user()->dailyUpdates()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    foreach (array_keys(DailyUpdate::SECTIONS) as $section) {
                        // whereLike ignores case on every database; a plain "like" is case-sensitive on Postgres.
                        $query->orWhereLike($section, '%'.$search.'%', caseSensitive: false);
                    }
                });
            })
            ->latest('date')
            ->paginate(10);
    }

    /**
     * Whether an update has been saved for today.
     */
    #[Computed]
    public function hasTodaysUpdate(): bool
    {
        return Auth::user()->dailyUpdates()->whereDate('date', today())->exists();
    }

    public function render(): View
    {
        return view('livewire.updates.index');
    }
}
