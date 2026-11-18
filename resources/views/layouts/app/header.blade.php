<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark" @desktop data-desktop @enddesktop>
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-950 text-zinc-100 antialiased">
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
            {{-- The Mac window's toolbar: the title bar is hidden, so this strip holds the window buttons and moves the window. --}}
            <header class="app-drag sticky top-0 z-30 border-b border-[#1a2133] bg-zinc-950/85 backdrop-blur-xl">
                <div class="relative flex h-13 items-center ps-20 pe-3">
                    <nav aria-label="{{ __('Main') }}" class="app-no-drag absolute start-1/2 flex -translate-x-1/2 gap-0.5 rounded-lg bg-[#131a2b] p-0.5">
                        @foreach ($navItems as $item)
                            <a
                                href="{{ $item['href'] }}"
                                @if ($item['current']) aria-current="page" @endif
                                @class([
                                    'flex h-7 items-center rounded-md px-3 text-[13px] transition-colors',
                                    'bg-[#26304a] font-medium text-white shadow-sm' => $item['current'],
                                    'text-zinc-400 hover:text-zinc-100' => ! $item['current'],
                                ])
                                wire:navigate
                            >
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    </nav>

                    <span class="grow"></span>

                    <livewire:desktop.sync-status />

                    <flux:tooltip :content="__('Settings')" position="bottom">
                        <a
                            href="{{ route('profile.edit') }}"
                            class="app-no-drag grid size-8 place-items-center rounded-md text-zinc-400 transition-colors hover:bg-[#131a2b] hover:text-zinc-100"
                            aria-label="{{ __('Settings') }}"
                            wire:navigate
                        >
                            <flux:icon.cog-6-tooth variant="outline" class="size-[18px]" />
                        </a>
                    </flux:tooltip>
                </div>
            </header>
        @else
            <header class="border-b border-[#1a2133]">
                <div class="mx-auto flex min-h-16 w-full max-w-5xl flex-wrap items-center gap-x-6 gap-y-2 px-6">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5" wire:navigate>
                        <x-app-logo-icon class="size-7" />
                        <span class="text-[15px] font-semibold">{{ config('app.name', 'Daily Updates') }}</span>
                    </a>

                    <nav aria-label="{{ __('Main') }}" class="flex gap-1">
                        @foreach ($navItems as $item)
                            <a
                                href="{{ $item['href'] }}"
                                @if ($item['current']) aria-current="page" @endif
                                @class([
                                    'flex h-9 items-center rounded-full px-3 text-sm transition-colors',
                                    'bg-[#161d30] font-medium text-white' => $item['current'],
                                    'text-zinc-400 hover:text-white' => ! $item['current'],
                                ])
                                wire:navigate
                            >
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    </nav>

                    <span class="grow"></span>

                    <x-desktop-user-menu />
                </div>
            </header>
        @enddesktop

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
