{{-- Checks again every so often, so the time and any error stay current between syncs. --}}
<div wire:poll.60s>
    @if ($link)
        @php
            $label = match (true) {
                filled($link->last_error) => $link->last_error,
                $link->last_synced_at !== null => __('Synced :time', ['time' => $link->last_synced_at->diffForHumans()]),
                default => __('Not synced yet'),
            };
        @endphp

        <flux:tooltip :content="$label" position="bottom">
            <a
                href="{{ route('plan.edit') }}"
                class="grid size-8 place-items-center rounded-md text-ink-muted transition-colors hover:bg-hover hover:text-ink"
                aria-label="{{ $label }}"
                data-test="sync-status"
                wire:navigate
            >
                @if (filled($link->last_error))
                    <flux:icon.exclamation-triangle variant="outline" class="size-[18px] text-amber-600 dark:text-amber-300" />
                @else
                    <flux:icon.cloud variant="outline" class="size-[18px]" />
                @endif
            </a>
        </flux:tooltip>
    @endif
</div>
