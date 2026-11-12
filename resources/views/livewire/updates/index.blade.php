<section class="mx-auto w-full max-w-3xl space-y-8">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-3xl font-semibold tracking-tight">{{ __('History') }}</h1>

        <a
            href="{{ route('updates.show', today()->format('Y-m-d')) }}"
            class="inline-flex h-10 items-center gap-2 rounded-full bg-accent px-4.5 text-sm font-semibold text-accent-foreground transition-opacity hover:opacity-90"
            wire:navigate
        >
            {{ $this->hasTodaysUpdate ? __("Open today's update") : __("Start today's update") }}
            <flux:icon.arrow-right variant="micro" />
        </a>
    </div>

    <flux:input
        wire:model.live.debounce.300ms="search"
        icon="magnifying-glass"
        :placeholder="__('Search your updates')"
        :aria-label="__('Search updates')"
        clearable
        class="[&_input]:rounded-full! [&_input]:bg-zinc-900! [&_input]:border-[#1f2840]!"
    />

    <div>
        @forelse ($this->updates as $update)
            <article wire:key="update-{{ $update->id }}" class="grid grid-cols-[56px_minmax(0,1fr)] gap-5">
                <div class="flex flex-col items-center">
                    <a
                        href="{{ route('updates.show', $update->date->format('Y-m-d')) }}"
                        aria-label="{{ $update->date->format('l, F j, Y') }}"
                        @class([
                            'flex w-13 flex-col items-center rounded-xl border py-1.5 leading-tight',
                            'border-accent bg-accent text-accent-foreground' => $update->date->isToday(),
                            'border-[#1f2840] bg-[#111727] text-zinc-100 hover:border-zinc-600' => ! $update->date->isToday(),
                        ])
                        wire:navigate
                    >
                        <span @class(['font-mono text-[11px] uppercase', 'text-zinc-500' => ! $update->date->isToday()])>{{ $update->date->format('D') }}</span>
                        <span class="text-xl font-semibold">{{ $update->date->format('j') }}</span>
                    </a>

                    <span class="mt-2 w-px grow bg-[#1c2438]" aria-hidden="true"></span>
                </div>

                <div class="min-w-0 space-y-3.5 pb-8">
                    <div class="flex min-h-7 items-center gap-2.5">
                        <a href="{{ route('updates.show', $update->date->format('Y-m-d')) }}" class="text-[15px] font-semibold hover:text-white" wire:navigate>
                            {{ $update->date->format($update->date->isCurrentYear() ? 'l, F j' : 'l, F j, Y') }}
                        </a>

                        <span class="grow"></span>

                        <button
                            type="button"
                            class="h-8 cursor-pointer rounded-lg px-2.5 text-sm text-zinc-400 transition-colors hover:bg-zinc-800 hover:text-white"
                            x-data="{ copied: false }"
                            x-on:click="navigator.clipboard.writeText(@js($update->toPlainText())); copied = true; setTimeout(() => copied = false, 2000)"
                        >
                            <span x-text="copied ? @js(__('Copied')) : @js(__('Copy'))">{{ __('Copy') }}</span>
                        </button>
                    </div>

                    <x-daily-update :update="$update" :highlight="$search" />
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-zinc-700 px-6 py-14 text-center">
                @if (filled($search))
                    <p class="text-sm text-zinc-400">{{ __('No updates match ":search".', ['search' => $search]) }}</p>
                @else
                    <p class="font-semibold">{{ __('No updates yet') }}</p>
                    <p class="mt-1 text-sm text-zinc-400">{{ __("Start today's update to begin your history.") }}</p>
                @endif
            </div>
        @endforelse
    </div>

    <flux:pagination :paginator="$this->updates" />
</section>
