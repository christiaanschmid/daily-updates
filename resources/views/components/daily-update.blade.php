@props([
    'update',
    'highlight' => null,
    'sections' => \App\Models\DailyUpdate::SECTIONS,
    'completedTodos' => null,
])

@php
    $chipClasses = [
        'done' => 'bg-raised text-section-done',
        'up_next' => 'bg-accent/15 text-section-up-next',
    ];
@endphp

{{-- On a phone each section's chip sits above its text; from sm up the chips form a column beside it. --}}
<div {{ $attributes->class('grid grid-cols-1 gap-x-3 gap-y-4 sm:grid-cols-[minmax(92px,max-content)_minmax(0,1fr)] sm:gap-y-3') }}>
    @foreach ($sections as $section => $heading)
        <div class="flex flex-col gap-y-1.5 sm:col-span-2 sm:grid sm:grid-cols-subgrid sm:items-baseline">
            <span class="self-start justify-self-start rounded-md whitespace-nowrap px-2 py-0.5 text-xs font-medium {{ $chipClasses[$section] ?? '' }}">{{ __($heading) }}</span>

            @if (filled($update->{$section}))
                <div class="du-prose text-sm leading-relaxed text-ink-soft [&_mark]:rounded-sm [&_mark]:bg-yellow-300 [&_mark]:px-0.5 [&_mark]:text-zinc-950">
                    {!! $update->highlighted($section, $highlight) !!}
                </div>
            @else
                <span class="text-sm text-zinc-500">&mdash;</span>
            @endif
        </div>
    @endforeach

    @if ($completedTodos?->isNotEmpty())
        <div class="flex flex-col gap-y-1.5 sm:col-span-2 sm:grid sm:grid-cols-subgrid sm:items-baseline">
            <span class="self-start justify-self-start rounded-md whitespace-nowrap bg-teal-600/10 dark:bg-teal-300/10 px-2 py-0.5 text-xs font-medium text-teal-700 dark:text-teal-200">{{ __(\App\Models\DailyUpdate::COMPLETED_TODOS_HEADING) }}</span>

            <div class="du-prose text-sm leading-relaxed text-ink-soft">
                <ul>
                    @foreach ($completedTodos as $todo)
                        <li>{{ $todo->titleHtml() }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif
</div>
