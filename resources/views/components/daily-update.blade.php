@props([
    'update',
    'highlight' => null,
    'sections' => \App\Models\DailyUpdate::SECTIONS,
    'completedTodos' => null,
])

@php
    $chipClasses = [
        'done' => 'bg-[#161d30] text-section-done',
        'up_next' => 'bg-accent/15 text-section-up-next',
    ];
@endphp

<div {{ $attributes->class('grid grid-cols-[minmax(92px,max-content)_minmax(0,1fr)] gap-x-3 gap-y-3') }}>
    @foreach ($sections as $section => $heading)
        <div class="col-span-2 grid grid-cols-subgrid items-baseline">
            <span class="justify-self-start rounded-md whitespace-nowrap px-2 py-0.5 text-xs font-medium {{ $chipClasses[$section] ?? '' }}">{{ __($heading) }}</span>

            @if (filled($update->{$section}))
                <div class="du-prose text-sm leading-relaxed text-zinc-200 [&_mark]:rounded-sm [&_mark]:bg-yellow-300 [&_mark]:px-0.5 [&_mark]:text-zinc-950">
                    {!! $update->highlighted($section, $highlight) !!}
                </div>
            @else
                <span class="text-sm text-zinc-500">&mdash;</span>
            @endif
        </div>
    @endforeach

    @if ($completedTodos?->isNotEmpty())
        <div class="col-span-2 grid grid-cols-subgrid items-baseline">
            <span class="justify-self-start rounded-md whitespace-nowrap bg-teal-300/10 px-2 py-0.5 text-xs font-medium text-teal-200">{{ __(\App\Models\DailyUpdate::COMPLETED_TODOS_HEADING) }}</span>

            <div class="du-prose text-sm leading-relaxed text-zinc-200">
                <ul>
                    @foreach ($completedTodos as $todo)
                        <li>{{ $todo->titleHtml() }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif
</div>
