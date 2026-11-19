@php
    $card = 'rounded-2xl border border-line bg-surface p-5';
    $link = $this->cloudLink;
@endphp

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Plan settings') }}</flux:heading>

    <x-settings.layout
        :heading="__('Cloud sync')"
        :subheading="$link ? __('Syncing with :host', ['host' => $link->host()]) : __('Your logs are stored on this Mac')"
    >
        <div class="space-y-6">
            @if ($link)
                <div class="{{ $card }}" data-test="sync-on">
                    <p class="font-semibold text-ink">{{ __('Sync is on') }}</p>
                    <p class="mt-1 text-sm text-ink-muted">
                        {{ __('Your logs and todos are on this Mac and in your account on :host. Changes you make here or on the web show up in both places, even after you\'ve been offline.', ['host' => $link->host()]) }}
                    </p>

                    <p class="mt-3 text-sm text-ink-muted" data-test="last-synced">
                        @if ($link->last_synced_at)
                            {{ __('Last synced :time.', ['time' => $link->last_synced_at->diffForHumans()]) }}
                        @else
                            {{ __('Not synced yet.') }}
                        @endif
                    </p>

                    @if (filled($link->last_error))
                        <p class="mt-2 text-sm text-red-600 dark:text-red-300" data-test="sync-error">{{ $link->last_error }}</p>
                    @endif

                    <div class="mt-4 flex flex-wrap gap-2">
                        <flux:button variant="primary" wire:click="syncNow" data-test="sync-now">{{ __('Sync now') }}</flux:button>
                        <flux:modal.trigger name="turn-off-sync">
                            <flux:button variant="ghost">{{ __('Turn off sync') }}</flux:button>
                        </flux:modal.trigger>
                    </div>
                </div>

                <flux:modal name="turn-off-sync" class="max-w-md">
                    <div class="space-y-4">
                        <flux:heading size="lg">{{ __('Turn off sync?') }}</flux:heading>
                        <flux:text>{{ __('Everything stays on this Mac and in your account, but changes stop going between them. You can turn sync on again later.') }}</flux:text>
                        <div class="flex justify-end gap-2">
                            <flux:modal.close>
                                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                            </flux:modal.close>
                            <flux:button variant="danger" wire:click="turnOffSync" data-test="turn-off-sync">{{ __('Turn off sync') }}</flux:button>
                        </div>
                    </div>
                </flux:modal>
            @elseif ($connectStatus === 'waiting')
                {{-- Checks every few seconds whether the user approved the connection in their browser. --}}
                <div class="{{ $card }}" wire:poll.2s="checkConnection" data-test="connect-waiting">
                    <p class="font-semibold text-ink">{{ __('Approve in your browser') }}</p>
                    <p class="mt-1 text-sm text-ink-muted">
                        {{ __('Sign in to :host in the browser window that just opened and click Connect my Mac. This page continues by itself once you do.', ['host' => $cloudHost]) }}
                    </p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <flux:button wire:click="openApprovalPage">{{ __('Open the page again') }}</flux:button>
                        <flux:button variant="ghost" wire:click="cancelConnecting">{{ __('Cancel') }}</flux:button>
                    </div>
                </div>
            @else
                <div class="{{ $card }}" data-test="sync-off">
                    <p class="font-semibold text-ink">{{ __('Use your logs on any device') }}</p>
                    <p class="mt-1 text-sm text-ink-muted">
                        {{ __('Sync this Mac with your account on :host to see and write your logs on the web and on other computers too. The app keeps working offline and catches up when you\'re back online. This needs the cloud plan, which has a free trial.', ['host' => $cloudHost]) }}
                    </p>
                    <p class="mt-2 text-sm text-ink-muted">
                        {{ __('Logs already in your account are combined with this Mac\'s: a day logged in both places keeps both sets of notes.') }}
                    </p>

                    @if (filled($connectStatus))
                        <p class="mt-3 text-sm text-red-600 dark:text-red-300" data-test="connect-error">{{ $connectStatus }}</p>
                    @endif

                    <flux:button class="mt-4" variant="primary" wire:click="turnOnSync" data-test="turn-on-sync">{{ __('Turn on sync') }}</flux:button>
                </div>
            @endif
        </div>
    </x-settings.layout>
</section>
