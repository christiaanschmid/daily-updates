<?php

namespace App\Livewire\Updates;

use App\Events\CloudSynced;
use App\Models\DailyUpdate;
use App\Sync\LogMerge;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * @property-read DailyUpdate|null $update
 * @property-read DailyUpdate|null $previousUpdate
 * @property-read DailyUpdate|null $nextUpdate
 * @property-read bool $hasCompletedTodos
 * @property-read bool $locked
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
     * The sections the editors' text is based on: the saved update they started from,
     * or last saved, or null when there was none. If the saved update no longer
     * matches it, it was changed elsewhere, such as on another device, and saving
     * combines the two instead of writing over the other change.
     *
     * @var array<string, string|null>|null
     */
    #[Locked]
    public ?array $base = null;

    /**
     * Mount the component for the given day, starting a new update when none exists yet.
     */
    public function mount(string $date): void
    {
        abort_unless(self::isValidDate($date), 404);

        $this->date = $date;
        $this->selectedDate = $date;

        if ($this->locked) {
            return;
        }

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

        $moveTo = Carbon::createFromFormat('!Y-m-d', $date);

        if (! $this->startedHere || ! Auth::user()->canSeeDay($moveTo) || Auth::user()->dailyUpdates()->whereDate('date', $date)->exists()) {
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
        abort_if($this->locked, 403);

        // Start from the update as it's saved now, which may have changed since the page opened.
        unset($this->update);

        if ($this->update) {
            $this->fillFromUpdate();
        }

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
        abort_if($this->locked, 403);

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
        return Auth::user()->visibleUpdates()
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
        return Auth::user()->visibleUpdates()
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

    /**
     * Whether this day is older than a free web account can see.
     */
    #[Computed]
    public function locked(): bool
    {
        return ! Auth::user()->canSeeDay($this->day());
    }

    /**
     * Show changes made elsewhere: on the Mac, after a sync brings them in, and on
     * the web, checked every so often. An open editor gets the change too, once its
     * typing is saved; see acknowledgeRefresh().
     */
    #[On('native:'.CloudSynced::class)]
    public function checkForChanges(): void
    {
        unset($this->update, $this->previousUpdate, $this->nextUpdate, $this->hasCompletedTodos);

        if (self::sectionsOf($this->update) === $this->base) {
            $this->skipRender();

            return;
        }

        if (! $this->editing) {
            if ($this->update) {
                $this->fillFromUpdate();
            }

            return;
        }

        // Deleted elsewhere while being edited here: the edit wins, and saving brings it back.
        if ($this->update) {
            $this->sendToEditors($this->update);
        }
    }

    /**
     * The editors now show the saved update with this fingerprint, so saving them may
     * write over it. If it has changed again since, the next save combines it again.
     */
    public function acknowledgeRefresh(string $hash): void
    {
        unset($this->update);

        if ($this->update !== null && self::fingerprint(self::sectionsOf($this->update)) === $hash) {
            $this->fillFromUpdate();
        }

        $this->skipRender();
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
        abort_if($this->locked, 403);

        $validated = $this->validate([
            'done' => ['nullable', 'string', 'max:'.DailyUpdate::MAX_SECTION_LENGTH],
            'up_next' => ['nullable', 'string', 'max:'.DailyUpdate::MAX_SECTION_LENGTH],
        ]);

        // Always the update as it's saved right now, to notice a change made elsewhere.
        unset($this->update);

        $hasContent = collect(array_keys(DailyUpdate::SECTIONS))
            ->contains(fn (string $section) => filled(strip_tags($this->{$section})));

        if (! $this->update && ! $hasContent) {
            return;
        }

        $saved = $this->update;

        if ($saved !== null && self::sectionsOf($saved) !== $this->base) {
            $this->combineWith($saved, $validated);
        } else {
            $saved = Auth::user()->dailyUpdates()->updateOrCreate(['date' => $this->day()], $validated);
            $this->base = self::sectionsOf($saved);
        }

        unset($this->update);

        $this->lastSavedAt = now()->format('g:i A');
    }

    /**
     * The update changed elsewhere since the editors loaded it: apply both changes,
     * and show the result in the editors.
     *
     * @param  array<string, string|null>  $validated
     */
    protected function combineWith(DailyUpdate $saved, array $validated): void
    {
        $mine = collect(LogMerge::SECTIONS)->mapWithKeys(fn (string $section) => [$section => DailyUpdate::sanitize($validated[$section] ?? null)])->all();

        foreach (LogMerge::SECTIONS as $section) {
            $saved->{$section} = LogMerge::threeWay($this->base[$section] ?? null, $mine[$section], $saved->{$section});
        }

        if ($saved->isDirty()) {
            $saved->save();
        }

        // The editors' text is what they sent, until they show the combined update.
        $this->base = $mine;

        if (self::sectionsOf($saved) !== $mine) {
            $this->sendToEditors($saved);
        }
    }

    /**
     * Give the open editors the saved update. Each one shows it once the person
     * pauses typing, then confirms with acknowledgeRefresh().
     */
    protected function sendToEditors(DailyUpdate $saved): void
    {
        $this->dispatch(
            'log-refreshed',
            sections: collect(LogMerge::SECTIONS)->mapWithKeys(fn (string $section) => [$section => (string) $saved->{$section}])->all(),
            hash: self::fingerprint(self::sectionsOf($saved)),
        );
    }

    /**
     * A saved update's sections, or null when there is none.
     *
     * @return array<string, string|null>|null
     */
    protected static function sectionsOf(?DailyUpdate $update): ?array
    {
        return $update === null ? null : collect(LogMerge::SECTIONS)->mapWithKeys(fn (string $section) => [$section => $update->{$section}])->all();
    }

    /**
     * A short fingerprint of sections, for the editors to confirm which version they show.
     *
     * @param  array<string, string|null>|null  $sections
     */
    protected static function fingerprint(?array $sections): string
    {
        return md5((string) json_encode($sections));
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
        $this->base = self::sectionsOf($this->update);
    }
}
