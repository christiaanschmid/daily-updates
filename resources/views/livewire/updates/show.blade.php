@php
    $day = \Illuminate\Support\Carbon::parse($date);

    $sectionStyles = [
        'done' => ['chip' => 'bg-raised text-section-done', 'hint' => __('What you got done today')],
        'up_next' => ['chip' => 'bg-accent/15 text-section-up-next', 'hint' => __('What you plan to do next')],
    ];

    $navButton = 'grid size-10 shrink-0 place-items-center rounded-full border border-line text-ink-muted transition-colors hover:border-line-hover hover:text-ink-strong sm:size-9';
    $barButton = 'inline-flex h-10 cursor-pointer items-center gap-2 rounded-[10px] px-3 text-sm text-ink-soft transition-colors hover:bg-hover hover:text-ink-strong sm:h-9';
    $barPrimary = 'inline-flex h-10 cursor-pointer items-center gap-2 rounded-[10px] bg-accent px-4 text-sm font-semibold text-accent-foreground transition-opacity hover:opacity-90 sm:h-9';
@endphp

{{-- On the web, picks up changes made elsewhere, such as in the Mac app. The Mac app hears about them when it syncs. --}}
<div class="mx-auto w-full max-w-[720px]" @unlessdesktop wire:poll.20s="checkForChanges" @enddesktop>
    <div class="space-y-3.5">
        <div class="flex flex-wrap items-center gap-2">
            @if ($this->previousUpdate)
                <a href="{{ route('updates.show', $this->previousUpdate->date->format('Y-m-d')) }}" class="{{ $navButton }}" aria-label="{{ __('Previous update') }}" wire:navigate>
                    <flux:icon.chevron-left variant="micro" />
                </a>
            @else
                <span class="{{ $navButton }} pointer-events-none border-line-faint text-ink-faint" aria-hidden="true">
                    <flux:icon.chevron-left variant="micro" />
                </span>
            @endif

            <x-date-picker property="selectedDate" />

            @if ($this->nextUpdate)
                <a href="{{ route('updates.show', $this->nextUpdate->date->format('Y-m-d')) }}" class="{{ $navButton }}" aria-label="{{ __('Next update') }}" wire:navigate>
                    <flux:icon.chevron-right variant="micro" />
                </a>
            @else
                <span class="{{ $navButton }} pointer-events-none border-line-faint text-ink-faint" aria-hidden="true">
                    <flux:icon.chevron-right variant="micro" />
                </span>
            @endif

            @unless ($day->isToday())
                <a href="{{ route('updates.show', today()->format('Y-m-d')) }}" class="ms-1 inline-flex min-h-10 items-center text-sm sm:min-h-0 text-accent-content hover:underline" wire:navigate>
                    {{ __('Jump to today') }}
                </a>
            @endunless
        </div>

        <h1 class="text-3xl leading-tight font-semibold tracking-tight sm:text-4xl">
            {{ $day->format($day->isCurrentYear() ? 'l, F j' : 'l, F j, Y') }}
        </h1>

        <p class="text-sm text-ink-muted">
            @if ($editing)
                {{ __('Edit anything; it saves as you type.') }}
            @else
                {{ __('Daily huddle') }}
            @endif
        </p>
    </div>

    {{-- Hidden along with Up next: the previous day's plan, shown for reference.
    @if ($editing && filled($this->previousUpdate?->up_next))
        <details class="mt-7 rounded-xl border border-line bg-surface px-4 py-3">
            <summary class="cursor-pointer text-[13px] text-ink-muted hover:text-ink-soft">
                {{ __('What you planned on :date', ['date' => $this->previousUpdate->date->format('l, F j')]) }}
            </summary>

            <div class="du-prose mt-2 text-sm leading-relaxed text-ink-soft">
                {!! $this->previousUpdate->up_next !!}
            </div>
        </details>
    @endif
    --}}

    @if ($this->locked)
        <div class="mt-9 rounded-2xl border border-dashed border-line-strong px-6 py-12 text-center" data-test="history-limit-notice">
            <p class="font-semibold text-ink">{{ __('This day is outside your last :days days', ['days' => \App\Models\User::FREE_HISTORY_DAYS]) }}</p>
            <p class="mx-auto mt-2 max-w-md text-sm text-ink-muted">
                @if ($this->update)
                    {{ __('The free plan shows your last :days days of logs. Your log for this day is kept, and it shows again when you upgrade to the cloud plan.', ['days' => \App\Models\User::FREE_HISTORY_DAYS]) }}
                @else
                    {{ __('The free plan shows and writes logs for your last :days days. Upgrade to the cloud plan to log any day.', ['days' => \App\Models\User::FREE_HISTORY_DAYS]) }}
                @endif
            </p>
            <a href="{{ route('plan.edit') }}" class="mt-4 inline-flex text-sm font-semibold text-accent-content hover:underline" wire:navigate>{{ __('See the cloud plan') }}</a>
        </div>
    @else
    @if ($day->isToday())
        <div class="mt-9">
            <livewire:todos.panel wire:key="todos" />
        </div>
    @endif

    @if ($editing)
        <form wire:submit="save">
            <div @class(['space-y-8', 'mt-8' => $day->isToday(), 'mt-9' => ! $day->isToday()])>
                @foreach (\App\Models\DailyUpdate::SECTIONS as $section => $heading)
                    <div class="space-y-2.5" wire:key="section-{{ $section }}">
                        <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1">
                            <span class="shrink-0 rounded-md px-2.5 py-0.5 text-[13px] font-semibold {{ $sectionStyles[$section]['chip'] ?? '' }}">{{ __($heading) }}</span>
                            <span class="text-[13px] text-zinc-500">{{ $sectionStyles[$section]['hint'] ?? '' }}</span>
                        </div>

                        <x-rich-editor
                            :property="$section"
                            :label="__($heading)"
                            :autofocus="$section === 'done' && $startedHere"
                            wire:key="editor-{{ $section }}"
                        />
                    </div>
                @endforeach

                @if ($day->isToday() || $this->hasCompletedTodos)
                    <livewire:todos.completed :date="$date" wire:key="completed-todos-{{ $date }}" />
                @endif
            </div>

            <div class="sticky bottom-0 flex justify-center pt-6 pb-[max(1.5rem,env(safe-area-inset-bottom))]">
                <div class="flex max-w-full flex-wrap items-center justify-center gap-1 rounded-2xl border border-line bg-overlay p-1.5 shadow-[0_12px_32px_var(--color-shadow)]" data-action-bar>
                    <span class="inline-flex items-center gap-1.5 px-2 text-[13px] text-ink-muted" wire:loading.remove wire:target="done,up_next">
                        <span class="size-1.5 rounded-full bg-teal-500 dark:bg-teal-300" aria-hidden="true"></span>
                        @if ($lastSavedAt)
                            {{ __('Saved at :time', ['time' => $lastSavedAt]) }}
                        @else
                            {{ __('Autosave on') }}
                        @endif
                    </span>

                    <span class="inline-flex items-center gap-1.5 px-2 text-[13px] text-ink-muted" wire:loading wire:target="done,up_next">
                        {{ __('Saving…') }}
                    </span>

                    @if ($this->update)
                        <button
                            type="button"
                            class="{{ $barButton }}"
                            x-data="{ copied: false }"
                            x-on:click="navigator.clipboard.writeText(@js($this->update->toPlainText())); copied = true; setTimeout(() => copied = false, 2000)"
                        >
                            <span x-text="copied ? @js(__('Copied')) : @js(__('Copy as text'))">{{ __('Copy as text') }}</span>
                        </button>
                    @endif

                    <button type="submit" class="{{ $barPrimary }}">{{ __('Done') }}</button>
                </div>
            </div>
        </form>
    @else
        <div @class(['space-y-8', 'mt-8' => $day->isToday(), 'mt-9' => ! $day->isToday()])>
            @foreach (\App\Models\DailyUpdate::SECTIONS as $section => $heading)
                <div class="space-y-2.5">
                    <span class="inline-block rounded-md px-2.5 py-0.5 text-[13px] font-semibold {{ $sectionStyles[$section]['chip'] ?? '' }}">{{ __($heading) }}</span>

                    @if (filled($this->update->{$section}))
                        <div class="du-prose px-4 text-base leading-relaxed text-ink">
                            {!! $this->update->{$section} !!}
                        </div>
                    @else
                        <p class="px-4 text-base text-zinc-500">{{ __('Nothing noted.') }}</p>
                    @endif
                </div>
            @endforeach

            @if ($day->isToday() || $this->hasCompletedTodos)
                <livewire:todos.completed :date="$date" wire:key="completed-todos-{{ $date }}" />
            @endif
        </div>

        <div class="sticky bottom-0 flex justify-center pt-6 pb-[max(1.5rem,env(safe-area-inset-bottom))]">
            <div class="flex max-w-full flex-wrap items-center justify-center gap-1 rounded-2xl border border-line bg-overlay p-1.5 shadow-[0_12px_32px_var(--color-shadow)]" data-action-bar>
                <flux:modal.trigger name="confirm-update-deletion">
                    <button type="button" class="{{ $barButton }} text-ink-muted hover:text-red-600 dark:hover:text-red-300">
                        <flux:icon.trash variant="micro" />
                        {{ __('Delete') }}
                    </button>
                </flux:modal.trigger>

                <button
                    type="button"
                    class="{{ $barButton }}"
                    x-data="{ copied: false }"
                    x-on:click="navigator.clipboard.writeText(@js($this->update->toPlainText())); copied = true; setTimeout(() => copied = false, 2000)"
                >
                    <flux:icon.clipboard-document variant="micro" />
                    <span x-text="copied ? @js(__('Copied')) : @js(__('Copy as text'))">{{ __('Copy as text') }}</span>
                </button>

                <button type="button" class="{{ $barPrimary }}" wire:click="edit">
                    <flux:icon.pencil-square variant="micro" />
                    {{ __('Edit') }}
                </button>
            </div>
        </div>

        <flux:modal name="confirm-update-deletion" class="max-w-lg">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Delete this update?') }}</flux:heading>
                    <flux:subheading>{{ __('This permanently removes your notes for this day.') }}</flux:subheading>
                </div>

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>

                    <flux:button variant="danger" wire:click="delete">{{ __('Delete update') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
    @endif
</div>
