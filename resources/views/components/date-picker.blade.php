@props([
    'property',
    'label' => __('Change date'),
])

@php
    $arrowButton = 'grid size-8 cursor-pointer place-items-center rounded-lg text-zinc-400 transition-colors hover:bg-zinc-800 hover:text-white';
@endphp

{{--
    A calendar popover for a Livewire property holding a Y-m-d date (see resources/js/date-picker.js).
--}}
<div
    {{ $attributes->class('relative') }}
    x-data="datePicker({ property: @js($property) })"
    x-on:keydown.escape.prevent.stop="open && close()"
    x-on:click.outside="open && close(false)"
>
    <button
        type="button"
        x-ref="trigger"
        x-on:click="toggle()"
        x-bind:aria-expanded="open ? 'true' : 'false'"
        aria-haspopup="dialog"
        aria-label="{{ $label }}"
        class="inline-flex h-9 w-44 cursor-pointer items-center justify-center gap-2 rounded-full border border-[#1f2840] bg-zinc-900 px-4 font-mono text-sm text-zinc-200 transition-colors hover:border-zinc-600 hover:text-white"
        x-bind:class="open && 'border-zinc-600 text-white'"
    >
        <flux:icon.calendar variant="micro" class="text-zinc-500" />
        <span x-text="label"></span>
    </button>

    <div
        x-ref="panel"
        x-show="open"
        x-cloak
        x-transition.origin.top.duration.100ms
        role="dialog"
        aria-label="{{ __('Choose a date') }}"
        class="absolute top-full left-1/2 z-30 mt-2 w-[284px] -translate-x-1/2 rounded-2xl border border-[#1f2840] bg-[#111727] p-3 shadow-[0_16px_40px_rgba(0,0,0,0.5)]"
    >
        <div class="mb-2 flex items-center justify-between ps-2">
            <span class="text-sm font-semibold text-white" x-text="monthLabel"></span>

            <div class="flex items-center gap-0.5">
                <button type="button" class="{{ $arrowButton }}" x-on:click="shiftMonth(-1)" aria-label="{{ __('Previous month') }}">
                    <flux:icon.chevron-left variant="micro" />
                </button>

                <button type="button" class="{{ $arrowButton }}" x-on:click="shiftMonth(1)" aria-label="{{ __('Next month') }}">
                    <flux:icon.chevron-right variant="micro" />
                </button>
            </div>
        </div>

        <div class="grid grid-cols-7 gap-0.5 text-center">
            <template x-for="weekday in weekdays" :key="weekday">
                <span class="pb-1.5 text-[11px] font-medium tracking-wide text-zinc-500 uppercase" x-text="weekday"></span>
            </template>

            <template x-for="day in days" :key="day.iso">
                <button
                    type="button"
                    x-on:click="pick(day.iso)"
                    x-bind:aria-label="day.label"
                    x-bind:aria-current="day.iso === today ? 'date' : null"
                    x-bind:data-selected="day.iso === selected ? true : null"
                    x-bind:class="{
                        'bg-accent font-semibold text-accent-foreground hover:bg-accent': day.iso === selected,
                        'ring-1 ring-inset ring-accent/60 text-accent-content': day.iso === today && day.iso !== selected,
                        'text-zinc-200': day.inMonth && day.iso !== selected && day.iso !== today,
                        'text-zinc-600': ! day.inMonth && day.iso !== selected,
                    }"
                    class="grid h-9 cursor-pointer place-items-center rounded-lg font-mono text-[13px] transition-colors hover:bg-zinc-800"
                    x-text="day.day"
                ></button>
            </template>
        </div>

        <div class="mt-2 flex justify-end border-t border-[#1f2840] pt-2">
            <button
                type="button"
                class="cursor-pointer rounded-lg px-2.5 py-1 text-[13px] text-accent-content transition-colors hover:bg-zinc-800"
                x-on:click="pick(today)"
            >
                {{ __('Today') }}
            </button>
        </div>
    </div>
</div>
