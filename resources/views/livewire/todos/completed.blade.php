@php
    $isToday = $this->isToday();
@endphp

<div class="space-y-2.5">
    <div class="flex items-center gap-2.5">
        <span class="rounded-md bg-teal-300/10 px-2.5 py-0.5 text-[13px] font-semibold text-teal-200">{{ __(\App\Models\DailyUpdate::COMPLETED_TODOS_HEADING) }}</span>

        @if ($isToday)
            <span class="text-[13px] text-zinc-500">{{ __('Uncheck one to put it back on your list') }}</span>
        @endif
    </div>

    @if ($this->todos->isEmpty())
        <p class="px-4 text-base text-zinc-500">{{ __('Nothing checked off yet.') }}</p>
    @else
        <ul>
            @foreach ($this->todos as $todo)
                <li wire:key="completed-todo-{{ $todo->id }}-{{ md5($todo->title) }}" @class(['flex items-start gap-3 rounded-xl px-4 py-1.5', 'transition-colors hover:bg-zinc-900/70 has-[input:focus]:bg-zinc-900' => $isToday])>
                    @if ($isToday)
                        <button
                            type="button"
                            wire:click="reopen({{ $todo->id }})"
                            class="mt-[5px] grid size-[18px] shrink-0 cursor-pointer place-items-center rounded-[5px] border border-accent bg-accent text-accent-foreground transition-opacity hover:opacity-80"
                            aria-label="{{ __('Reopen :title', ['title' => $todo->title]) }}"
                        >
                            <flux:icon.check variant="micro" class="size-3.5" />
                        </button>
                    @else
                        <span class="mt-[5px] grid size-[18px] shrink-0 place-items-center rounded-[5px] border border-accent/60 bg-accent/60 text-accent-foreground" aria-hidden="true">
                            <flux:icon.check variant="micro" class="size-3.5" />
                        </span>
                    @endif

                    <x-todo-title :todo="$todo" :editable="$isToday" />
                </li>
            @endforeach
        </ul>
    @endif
</div>
