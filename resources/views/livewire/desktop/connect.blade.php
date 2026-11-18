@php
    $card = 'rounded-2xl border border-[#1f2840] bg-[#111727] p-6';
@endphp

<section class="mx-auto w-full max-w-lg space-y-6">
    <h1 class="text-3xl font-semibold tracking-tight">{{ __('Connect your Mac') }}</h1>

    <div class="{{ $card }}" data-test="connect-{{ $state }}">
        @switch($state)
            @case('ready')
                <p class="font-semibold text-zinc-100">{{ __('Sync this Mac with your account?') }}</p>
                <p class="mt-2 text-sm text-zinc-400">
                    {{ __('The Daily Updates app on your Mac will sync its logs and todos with :email, from now on. Days you already have here keep their notes, with the Mac\'s added below.', ['email' => auth()->user()->email]) }}
                </p>
                <flux:button class="mt-5" variant="primary" wire:click="approve" data-test="approve-connection">{{ __('Connect my Mac') }}</flux:button>
                <p class="mt-3 text-xs text-zinc-500">{{ __('Only approve this if you just clicked Turn on sync in the Mac app.') }}</p>
                @break

            @case('approved')
                <p class="font-semibold text-zinc-100">{{ __('Connected') }}</p>
                <p class="mt-2 text-sm text-zinc-400">{{ __('Go back to the Mac app. It\'s syncing your logs now. You can close this tab.') }}</p>
                @break

            @case('needs-plan')
                <p class="font-semibold text-zinc-100">{{ __('Sync needs the cloud plan') }}</p>
                <p class="mt-2 text-sm text-zinc-400">{{ __('Start your free trial, then come back to this page to connect your Mac.') }}</p>
                <a href="{{ route('plan.edit') }}" class="mt-4 inline-flex text-sm font-semibold text-accent-content hover:underline" wire:navigate>{{ __('See the cloud plan') }}</a>
                @break

            @case('expired')
                <p class="font-semibold text-zinc-100">{{ __('This link has expired') }}</p>
                <p class="mt-2 text-sm text-zinc-400">{{ __('Click Turn on sync in the Mac app again to get a new one.') }}</p>
                @break

            @default
                <p class="font-semibold text-zinc-100">{{ __('This link has already been used') }}</p>
                <p class="mt-2 text-sm text-zinc-400">{{ __('Click Turn on sync in the Mac app again to get a new one.') }}</p>
        @endswitch
    </div>
</section>
