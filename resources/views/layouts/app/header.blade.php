<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @desktop data-desktop @enddesktop>
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-canvas text-ink antialiased">
        @php
            $todayDate = today()->format('Y-m-d');
            $onToday = request()->routeIs('updates.show') && request()->route('date') === $todayDate;
            $navItems = [
                ['label' => __('Today'), 'href' => route('updates.show', $todayDate), 'current' => $onToday],
                ['label' => __('Huddle'), 'href' => route('huddle'), 'current' => request()->routeIs('huddle')],
                ['label' => __('History'), 'href' => route('dashboard'), 'current' => request()->routeIs('dashboard')],
            ];
        @endphp

        @desktop
            {{--
                The Mac window's toolbar: the title bar is hidden, so this strip holds the window buttons.
                Only its empty parts move the window. A button inside a dragging area can lose its clicks
                to the window drag, so no button sits inside one.
            --}}
            {{-- The page scrolls in `.desktop-scroll` below, so the scrollbar starts under this toolbar. --}}
            <header class="z-30 shrink-0 border-b border-line bg-canvas select-none">
                {{-- Three columns, so the centered nav can never cover the buttons on the right, however narrow the window. --}}
                <div class="grid h-13 grid-cols-[1fr_auto_1fr] items-center">
                    {{-- Starts after the window buttons, which macOS draws over this corner. --}}
                    <span class="app-drag ms-20 self-stretch"></span>

                    <nav aria-label="{{ __('Main') }}" class="mx-2 flex gap-0.5 rounded-lg bg-raised p-0.5">
                        @foreach ($navItems as $item)
                            <a
                                href="{{ $item['href'] }}"
                                @if ($item['current']) aria-current="page" @endif
                                @class([
                                    'flex h-7 items-center rounded-md px-3 text-[13px] transition-colors',
                                    'bg-raised-strong font-medium text-ink-strong shadow-sm' => $item['current'],
                                    'text-ink-muted hover:text-ink' => ! $item['current'],
                                ])
                                wire:navigate
                            >
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    </nav>

                    <div class="flex items-center gap-1 self-stretch pe-3">
                        <span class="app-drag grow self-stretch"></span>

                        <livewire:desktop.sync-status />

                        <a
                            href="{{ route('profile.edit') }}"
                            class="grid size-8 place-items-center rounded-md text-ink-muted transition-colors hover:bg-hover hover:text-ink"
                            aria-label="{{ __('Settings') }}"
                            wire:navigate
                        >
                            <flux:icon.cog-6-tooth variant="outline" class="size-[18px]" />
                        </a>
                    </div>
                </div>
            </header>
        @else
            <header class="border-b border-line">
                {{-- One row at every width: on a phone the name collapses to the logo so the nav fits beside it. --}}
                <div class="mx-auto flex min-h-16 w-full max-w-5xl items-center gap-x-3 px-4 sm:gap-x-6 sm:px-6">
                    <a href="{{ route('dashboard') }}" class="flex shrink-0 items-center gap-2.5" aria-label="{{ config('app.name', 'Daily Updates') }}" wire:navigate>
                        <x-app-logo-icon class="size-7" />
                        <span class="text-[15px] font-semibold max-sm:hidden">{{ config('app.name', 'Daily Updates') }}</span>
                    </a>

                    <nav aria-label="{{ __('Main') }}" class="flex min-w-0 gap-0.5 sm:gap-1">
                        @foreach ($navItems as $item)
                            <a
                                href="{{ $item['href'] }}"
                                @if ($item['current']) aria-current="page" @endif
                                @class([
                                    'flex h-10 items-center rounded-full px-3 text-sm transition-colors sm:h-9',
                                    'bg-raised font-medium text-ink-strong' => $item['current'],
                                    'text-ink-muted hover:text-ink-strong' => ! $item['current'],
                                ])
                                wire:navigate
                            >
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    </nav>

                    <span class="grow"></span>

                    <x-desktop-user-menu class="shrink-0" />
                </div>
            </header>
        @enddesktop

        @desktop
            <div class="desktop-scroll">
                {{ $slot }}
            </div>
        @else
            {{ $slot }}
        @enddesktop

        @persist('toast')
            <flux:toast />
        @endpersist

        @fluxScripts
    </body>
</html>
