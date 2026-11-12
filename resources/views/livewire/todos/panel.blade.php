@php
    $row = 'flex items-start gap-3 rounded-xl px-4 py-1.5';
    $checkbox = 'mt-[5px] grid size-[18px] shrink-0 place-items-center rounded-[5px] border';
@endphp

<div class="space-y-2.5">
    <div class="flex items-center gap-2.5">
        <span class="rounded-md bg-teal-300/10 px-2.5 py-0.5 text-[13px] font-semibold text-teal-200">{{ __('Todos') }}</span>
        <span class="text-[13px] text-zinc-500">{{ __('Check one off when it’s done') }}</span>

        @if ($this->openTodos->isNotEmpty())
            <span class="ms-auto font-mono text-xs text-zinc-500">{{ trans_choice(':count open|:count open', $this->openTodos->count()) }}</span>
        @endif
    </div>

    <ul>
        @foreach ($this->openTodos as $todo)
            <li wire:key="todo-{{ $todo->id }}-{{ md5($todo->title) }}" class="group {{ $row }} transition-colors hover:bg-zinc-900/70 has-[input:focus]:bg-zinc-900">
                <button
                    type="button"
                    wire:click="complete({{ $todo->id }})"
                    class="{{ $checkbox }} cursor-pointer border-zinc-600 text-transparent transition-colors hover:border-accent hover:text-accent"
                    aria-label="{{ __('Check off :title', ['title' => $todo->title]) }}"
                >
                    <flux:icon.check variant="micro" class="size-3.5" />
                </button>

                <x-todo-title :todo="$todo" />

                <button
                    type="button"
                    wire:click="delete({{ $todo->id }})"
                    class="mt-[5px] shrink-0 cursor-pointer text-zinc-600 opacity-0 transition-opacity group-hover:opacity-100 hover:text-red-300 focus:opacity-100"
                    aria-label="{{ __('Delete :title', ['title' => $todo->title]) }}"
                >
                    <flux:icon.x-mark variant="micro" />
                </button>
            </li>
        @endforeach

        <li class="{{ $row }} transition-colors focus-within:bg-zinc-900 hover:bg-zinc-900/70">
            <span class="{{ $checkbox }} border-dashed border-zinc-700 text-zinc-600" aria-hidden="true">
                <flux:icon.plus variant="micro" class="size-3" />
            </span>

            <form wire:submit="add" class="min-w-0 grow">
                <input
                    type="text"
                    wire:model="title"
                    placeholder="{{ __('Add a todo…') }}"
                    aria-label="{{ __('New todo') }}"
                    maxlength="{{ \App\Models\Todo::MAX_TITLE_LENGTH }}"
                    class="w-full border-0 bg-transparent p-0 text-base leading-relaxed text-zinc-100 placeholder:text-zinc-500 focus:ring-0 focus:outline-none"
                />
            </form>
        </li>
    </ul>

    @error('title')
        <p class="px-4 text-[13px] text-red-300">{{ $message }}</p>
    @enderror
</div>
