<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
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

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
