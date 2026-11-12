@props([
    'property',
    'label',
    'autofocus' => false,
])

@php
    $buttons = [
        ['command' => "run('toggleBold')", 'active' => 'bold', 'icon' => 'bold', 'label' => __('Bold')],
        ['command' => "run('toggleItalic')", 'active' => 'italic', 'icon' => 'italic', 'label' => __('Italic')],
        ['command' => "run('toggleUnderline')", 'active' => 'underline', 'icon' => 'underline', 'label' => __('Underline')],
        ['command' => "run('toggleStrike')", 'active' => 'strike', 'icon' => 'strikethrough', 'label' => __('Strikethrough')],
        null,
        ['command' => "run('toggleBulletList')", 'active' => 'bulletList', 'icon' => 'list-bullet', 'label' => __('Bulleted list')],
        ['command' => "run('toggleOrderedList')", 'active' => 'orderedList', 'icon' => 'numbered-list', 'label' => __('Numbered list')],
        null,
        ['command' => 'toggleLink()', 'active' => 'link', 'icon' => 'link', 'label' => __('Link')],
        ['command' => "run('toggleCode')", 'active' => 'code', 'icon' => 'code-bracket', 'label' => __('Code')],
    ];

    $buttonClasses = 'grid size-8 cursor-pointer place-items-center rounded-lg text-zinc-400 transition-colors hover:bg-zinc-800 hover:text-white';
@endphp

{{--
    A Tiptap editor for a Livewire property (see resources/js/editor.js). The editor's own
    DOM is left alone by Livewire's morphing; changes reach the server through $wire.
--}}
<div
    {{ $attributes->class('du-editor group') }}
    x-data="richEditor({ property: @js($property), label: @js($label), autofocus: @js($autofocus) })"
    wire:ignore
>
    <div class="hidden items-center gap-0.5 border-b border-zinc-800 px-2 py-1.5 group-focus-within:flex" role="toolbar" aria-label="{{ __('Formatting') }}">
        @foreach ($buttons as $button)
            @if ($button === null)
                <span class="mx-1 h-5 w-px bg-zinc-800" aria-hidden="true"></span>
            @else
                <button
                    type="button"
                    class="{{ $buttonClasses }}"
                    x-bind:class="active.{{ $button['active'] }} && 'bg-zinc-800 text-white'"
                    x-bind:aria-pressed="active.{{ $button['active'] }} ? 'true' : 'false'"
                    x-on:mousedown.prevent
                    x-on:click="{{ $button['command'] }}"
                    aria-label="{{ $button['label'] }}"
                >
                    <flux:icon :icon="$button['icon']" variant="micro" />
                </button>
            @endif
        @endforeach

        <span class="grow"></span>

        <button type="button" class="{{ $buttonClasses }}" x-on:mousedown.prevent x-on:click="undo()" aria-label="{{ __('Undo') }}">
            <flux:icon.arrow-uturn-left variant="micro" />
        </button>

        <button type="button" class="{{ $buttonClasses }}" x-on:mousedown.prevent x-on:click="redo()" aria-label="{{ __('Redo') }}">
            <flux:icon.arrow-uturn-right variant="micro" />
        </button>
    </div>

    <div x-ref="content"></div>
</div>
