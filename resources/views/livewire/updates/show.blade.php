@php
    $day = \Illuminate\Support\Carbon::parse($date);

    $sectionStyles = [
        'done' => ['chip' => 'bg-[#161d30] text-section-done', 'hint' => __('What you got done today')],
        'up_next' => ['chip' => 'bg-accent/15 text-section-up-next', 'hint' => __('What you plan to do next')],
    ];

    $navButton = 'grid size-9 place-items-center rounded-full border border-[#1f2840] text-zinc-400 transition-colors hover:border-zinc-600 hover:text-white';
    $barButton = 'inline-flex h-9 cursor-pointer items-center gap-2 rounded-[10px] px-3 text-sm text-zinc-200 transition-colors hover:bg-zinc-700/60 hover:text-white';
    $barPrimary = 'inline-flex h-9 cursor-pointer items-center gap-2 rounded-[10px] bg-accent px-4 text-sm font-semibold text-accent-foreground transition-opacity hover:opacity-90';
@endphp

<div class="mx-auto w-full max-w-[720px]">
    <div class="space-y-3.5">
        <div class="flex flex-wrap items-center gap-2">
            @if ($this->previousUpdate)
                <a href="{{ route('updates.show', $this->previousUpdate->date->format('Y-m-d')) }}" class="{{ $navButton }}" aria-label="{{ __('Previous update') }}" wire:navigate>
                    <flux:icon.chevron-left variant="micro" />
                </a>
            @else
                <span class="{{ $navButton }} pointer-events-none border-[#161d30] text-zinc-600" aria-hidden="true">
                    <flux:icon.chevron-left variant="micro" />
                </span>
            @endif

            <x-date-picker property="selectedDate" />

            @if ($this->nextUpdate)
                <a href="{{ route('updates.show', $this->nextUpdate->date->format('Y-m-d')) }}" class="{{ $navButton }}" aria-label="{{ __('Next update') }}" wire:navigate>
                    <flux:icon.chevron-right variant="micro" />
                </a>
            @else
                <span class="{{ $navButton }} pointer-events-none border-[#161d30] text-zinc-600" aria-hidden="true">
                    <flux:icon.chevron-right variant="micro" />
                </span>
            @endif

            @unless ($day->isToday())
                <a href="{{ route('updates.show', today()->format('Y-m-d')) }}" class="ms-1 text-sm text-accent-content hover:underline" wire:navigate>
                    {{ __('Jump to today') }}
                </a>
            @endunless
        </div>

        <h1 class="text-4xl leading-tight font-semibold tracking-tight">
            {{ $day->format($day->isCurrentYear() ? 'l, F j' : 'l, F j, Y') }}
        </h1>

        <p class="text-sm text-zinc-400">
            @if ($editing)
                {{ __('Edit anything; it saves as you type.') }}
            @else
                {{ __('Daily huddle') }}
            @endif
        </p>
    </div>

    {{-- Hidden along with Up next: the previous day's plan, shown for reference.
    @if ($editing && filled($this->previousUpdate?->up_next))
        <details class="mt-7 rounded-xl border border-[#1f2840] bg-[#111727] px-4 py-3">
            <summary class="cursor-pointer text-[13px] text-zinc-400 hover:text-zinc-200">
                {{ __('What you planned on :date', ['date' => $this->previousUpdate->date->format('l, F j')]) }}
            </summary>

            <div class="du-prose mt-2 text-sm leading-relaxed text-zinc-300">
                {!! $this->previousUpdate->up_next !!}
            </div>
        </details>
    @endif
    --}}

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
                        <div class="flex items-center gap-2.5">
                            <span class="rounded-md px-2.5 py-0.5 text-[13px] font-semibold {{ $sectionStyles[$section]['chip'] ?? '' }}">{{ __($heading) }}</span>
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

            <div class="sticky bottom-0 flex justify-center pt-6 pb-6">
                <div class="flex flex-wrap items-center gap-1 rounded-2xl border border-zinc-700 bg-zinc-800 p-1.5 shadow-[0_12px_32px_rgba(0,0,0,0.45)]">
                    <span class="inline-flex items-center gap-1.5 px-2 text-[13px] text-zinc-400" wire:loading.remove wire:target="done,up_next">
                        <span class="size-1.5 rounded-full bg-teal-300" aria-hidden="true"></span>
                        @if ($lastSavedAt)
                            {{ __('Saved at :time', ['time' => $lastSavedAt]) }}
                        @else
                            {{ __('Autosave on') }}
                        @endif
                    </span>

                    <span class="inline-flex items-center gap-1.5 px-2 text-[13px] text-zinc-400" wire:loading wire:target="done,up_next">
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
                        <div class="du-prose px-4 text-base leading-relaxed text-zinc-100">
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

        <div class="sticky bottom-0 flex justify-center pt-6 pb-6">
            <div class="flex flex-wrap items-center gap-1 rounded-2xl border border-zinc-700 bg-zinc-800 p-1.5 shadow-[0_12px_32px_rgba(0,0,0,0.45)]">
                <flux:modal.trigger name="confirm-update-deletion">
                    <button type="button" class="{{ $barButton }} text-zinc-400 hover:text-red-300">
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
</div>
