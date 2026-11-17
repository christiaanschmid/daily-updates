@php
    $card = 'rounded-2xl border border-[#1f2840] bg-[#111727] p-5';
@endphp

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Plan settings') }}</flux:heading>

    <x-settings.layout :heading="__('Plan')" :subheading="__('Your logs are stored on this Mac')">
        <div class="space-y-6">
            @if ($moveStatus === 'waiting')
                {{-- Checks every few seconds whether the user approved the connection in their browser. --}}
                <div class="{{ $card }}" wire:poll.2s="checkMoveToCloud" data-test="move-waiting">
                    <p class="font-semibold text-zinc-100">{{ __('Approve in your browser') }}</p>
                    <p class="mt-1 text-sm text-zinc-400">
                        {{ __('Sign in to :host in the browser window that just opened and click Connect my Mac. This page continues by itself once you do.', ['host' => $cloudHost]) }}
                    </p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <flux:button wire:click="openApprovalPage">{{ __('Open the page again') }}</flux:button>
                        <flux:button variant="ghost" wire:click="cancelMoveToCloud">{{ __('Cancel') }}</flux:button>
                    </div>
                </div>
            @else
                <div class="{{ $card }}" data-test="move-start">
                    <p class="font-semibold text-zinc-100">{{ __('Use your logs on any device') }}</p>
                    <p class="mt-1 text-sm text-zinc-400">
                        {{ __('Move this Mac\'s logs and todos to your account on :host, then open them on the web, on other computers and here. This needs the cloud plan, which has a free trial.', ['host' => $cloudHost]) }}
                    </p>
                    <p class="mt-2 text-sm text-zinc-400">
                        {{ __('Your logs also stay on this Mac. To go back to them later, choose Use This Mac\'s Data Instead from the Cloud menu.') }}
                    </p>

                    @if (filled($moveStatus))
                        <p class="mt-3 text-sm text-red-300" data-test="move-error">{{ $moveStatus }}</p>
                    @endif

                    <flux:button class="mt-4" variant="primary" wire:click="moveToCloud" data-test="move-to-cloud">{{ __('Move to cloud') }}</flux:button>
                </div>
            @endif
        </div>
    </x-settings.layout>
</section>
