<section class="mx-auto w-full max-w-[720px] space-y-8">
    <div class="space-y-3.5">
        <h1 class="text-4xl leading-tight font-semibold tracking-tight">{{ __('Huddle') }}</h1>

        <p class="text-sm text-zinc-400">
            @if ($this->update)
                {{ __('From your log for :date.', ['date' => $this->update->date->format($this->update->date->isCurrentYear() ? 'l, F j' : 'l, F j, Y')]) }}
            @else
                {{ __("Log today's work and it will be ready for tomorrow's huddle.") }}
            @endif
        </p>
    </div>

    @if ($this->update)
        <x-daily-update :update="$this->update" :sections="\App\Models\DailyUpdate::HUDDLE_HEADINGS" :completed-todos="$this->update->completedTodos()" />

        <div class="flex flex-wrap items-center gap-2">
            <button
                type="button"
                class="inline-flex h-9 cursor-pointer items-center gap-2 rounded-[10px] bg-accent px-4 text-sm font-semibold text-accent-foreground transition-opacity hover:opacity-90"
                x-data="{ copied: false }"
                x-on:click="navigator.clipboard.writeText(@js($this->update->toHuddleText())); copied = true; setTimeout(() => copied = false, 2000)"
            >
                <flux:icon.clipboard-document variant="micro" />
                <span x-text="copied ? @js(__('Copied')) : @js(__('Copy for huddle'))">{{ __('Copy for huddle') }}</span>
            </button>

            <a
                href="{{ route('updates.show', $this->update->date->format('Y-m-d')) }}"
                class="inline-flex h-9 items-center gap-2 rounded-[10px] px-3 text-sm text-zinc-200 transition-colors hover:bg-zinc-700/60 hover:text-white"
                wire:navigate
            >
                <flux:icon.pencil-square variant="micro" />
                {{ __('Edit that log') }}
            </a>
        </div>
    @else
        <div class="rounded-2xl border border-dashed border-zinc-700 px-6 py-14 text-center">
            <p class="font-semibold">{{ __('Nothing to share yet') }}</p>
        </div>
    @endif
</section>
