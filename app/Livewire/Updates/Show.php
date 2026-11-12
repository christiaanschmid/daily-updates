<?php

namespace App\Livewire\Updates;

use App\Models\DailyUpdate;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * @property-read DailyUpdate|null $update
 * @property-read DailyUpdate|null $previousUpdate
 * @property-read DailyUpdate|null $nextUpdate
 * @property-read bool $hasCompletedTodos
 */
class Show extends Component
{
    /**
     * What an empty section holds in the editor, so typing starts on a bullet.
     */
    public const EMPTY_BULLET_LIST = '<ul><li><p></p></li></ul>';

    #[Locked]
    public string $date = '';

    public string $done = '';

    public string $up_next = '';

    public string $selectedDate = '';

    public bool $editing = false;

    /**
     * Whether this page started the update, so it may still move to another day.
     */
    #[Locked]
    public bool $startedHere = false;

    /**
     * When the update was last saved on this page, for the save status.
     */
    public ?string $lastSavedAt = null;

    /**
     * Mount the component for the given day, starting a new update when none exists yet.
     */
    public function mount(string $date): void
    {
        abort_unless(self::isValidDate($date), 404);

        $this->date = $date;
        $this->selectedDate = $date;

        if ($this->update) {
            $this->fillFromUpdate();

            return;
        }

        $this->startedHere = true;
        $this->edit();
    }

    /**
     * Save a section shortly after it changes.
     */
    public function updated(string $property): void
    {
        if ($this->editing && array_key_exists($property, DailyUpdate::SECTIONS)) {
            $this->persist();
        }
    }

    /**
     * Move to the picked day. An update started on this page moves there along with
     * what has been typed so far; otherwise the picked day's page is opened.
     */
    public function updatedSelectedDate(string $date): void
    {
        if (! self::isValidDate($date)) {
            $this->selectedDate = $this->date;

            return;
        }

        if ($date === $this->date) {
            return;
        }

        if (! $this->startedHere || Auth::user()->dailyUpdates()->whereDate('date', $date)->exists()) {
            $this->redirectRoute('updates.show', $date, navigate: true);

            return;
        }

        $this->update?->update(['date' => $date]);

        $this->date = $date;

        unset($this->update, $this->previousUpdate, $this->nextUpdate);

        $this->js('history.replaceState(history.state, "", '.json_encode(route('updates.show', $date)).')');
    }

    /**
     * Save the update and stop editing.
     */
    public function save(): void
    {
        $this->persist();

        if (! $this->update) {
            $this->redirectRoute('dashboard', navigate: true);

            return;
        }

        $this->editing = false;

        Flux::toast(variant: 'success', text: __('Update saved.'));
    }

    /**
     * Start editing the saved update.
     */
    public function edit(): void
    {
        foreach (array_keys(DailyUpdate::SECTIONS) as $section) {
            if (blank(strip_tags($this->{$section}))) {
                $this->{$section} = self::EMPTY_BULLET_LIST;
            }
        }

        $this->editing = true;
    }

    /**
     * Delete the update for the day.
     */
    public function delete(): void
    {
        $this->update?->delete();

        Flux::toast(text: __('Update deleted.'));

        $this->redirectRoute('dashboard', navigate: true);
    }

    /**
     * The saved update for the day, if any.
     */
    #[Computed]
    public function update(): ?DailyUpdate
    {
        return Auth::user()->dailyUpdates()->whereDate('date', $this->day())->first();
    }

    /**
     * The most recent saved update before this day.
     */
    #[Computed]
    public function previousUpdate(): ?DailyUpdate
    {
        return Auth::user()->dailyUpdates()
            ->whereDate('date', '<', $this->day())
            ->latest('date')
            ->first();
    }

    /**
     * The earliest saved update after this day.
     */
    #[Computed]
    public function nextUpdate(): ?DailyUpdate
    {
        return Auth::user()->dailyUpdates()
            ->whereDate('date', '>', $this->day())
            ->oldest('date')
            ->first();
    }

    /**
     * Whether any todos were checked off on this day.
     */
    #[Computed]
    public function hasCompletedTodos(): bool
    {
        return Auth::user()->todos()->completedOn($this->day())->exists();
    }

    public function render(): View
    {
        return view('livewire.updates.show')
            ->title(($this->update->date ?? now())->format('m/d/Y'));
    }

    /**
     * Store the sections for the day. A new update is only created once something
     * has been written in it.
     */
    protected function persist(): void
    {
        $validated = $this->validate([
            'done' => ['nullable', 'string', 'max:'.DailyUpdate::MAX_SECTION_LENGTH],
            'up_next' => ['nullable', 'string', 'max:'.DailyUpdate::MAX_SECTION_LENGTH],
        ]);

        $hasContent = collect(array_keys(DailyUpdate::SECTIONS))
            ->contains(fn (string $section) => filled(strip_tags($this->{$section})));

        if (! $this->update && ! $hasContent) {
            return;
        }

        Auth::user()->dailyUpdates()->updateOrCreate(['date' => $this->day()], $validated);

        unset($this->update);

        $this->lastSavedAt = now()->format('g:i A');
    }

    /**
     * Whether the given string is a real calendar day in Y-m-d format.
     */
    protected static function isValidDate(string $date): bool
    {
        return Carbon::canBeCreatedFromFormat($date, 'Y-m-d')
            && Carbon::createFromFormat('!Y-m-d', $date)->format('Y-m-d') === $date;
    }

    /**
     * The day this update is for.
     */
    protected function day(): Carbon
    {
        return Carbon::createFromFormat('!Y-m-d', $this->date);
    }

    /**
     * Copy the saved update's sections into the form.
     */
    protected function fillFromUpdate(): void
    {
        $this->done = (string) $this->update->done;
        $this->up_next = (string) $this->update->up_next;
    }
}
