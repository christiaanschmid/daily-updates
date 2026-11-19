@php
    $user = auth()->user();
    $subscription = $this->subscription;
    $card = 'rounded-2xl border border-line bg-surface p-5';
    $trialDays = config('services.paddle.trial_days');
@endphp

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Plan settings') }}</flux:heading>

    <x-settings.layout :heading="__('Plan')" :subheading="__('The cloud plan keeps your full history, on any device')">
        <div class="space-y-6">
            @if ($checkout === 'done' && ! $user->hasCloudPlan())
                {{-- Paddle sends the user back before its webhook arrives, so check again until it does. --}}
                <div class="{{ $card }}" wire:poll.2s data-test="plan-finishing">
                    <p class="font-semibold text-ink">{{ __('Finishing up…') }}</p>
                    <p class="mt-1 text-sm text-ink-muted">{{ __('Paddle is confirming your subscription. This page updates by itself in a moment.') }}</p>
                </div>
            @elseif ($subscription?->pastDue())
                <div class="{{ $card }} border-red-400/40" data-test="plan-past-due">
                    <p class="font-semibold text-ink">{{ __('Your last payment didn\'t go through') }}</p>
                    <p class="mt-1 text-sm text-ink-muted">{{ __('Update your payment method to keep the cloud plan. Until then, your account works like the free plan.') }}</p>
                    <flux:button class="mt-4" variant="primary" wire:click="updatePaymentMethod">{{ __('Update payment method') }}</flux:button>
                </div>
            @elseif ($subscription?->valid())
                <div class="{{ $card }}" data-test="plan-subscribed">
                    <p class="font-semibold text-ink">
                        {{ __('Cloud plan') }}
                        @if ($this->billingPeriod === 'yearly')
                            <span class="font-normal text-ink-muted">· {{ __('$50 a year') }}</span>
                        @elseif ($this->billingPeriod === 'monthly')
                            <span class="font-normal text-ink-muted">· {{ __('$5 a month') }}</span>
                        @endif
                    </p>

                    <p class="mt-1 text-sm text-ink-muted">
                        @if ($subscription->onGracePeriod())
                            {{ __('Canceled. You keep the cloud plan until :date, then your account moves to the free plan. Your logs are kept.', ['date' => $subscription->ends_at->format('F j, Y')]) }}
                        @elseif ($subscription->onTrial() && $subscription->trial_ends_at)
                            {{ __('Free trial until :date. Your first payment is on that day unless you cancel before then.', ['date' => $subscription->trial_ends_at->format('F j, Y')]) }}
                        @else
                            {{ __('Active. It renews automatically until you cancel.') }}
                        @endif
                    </p>

                    <div class="mt-4 flex flex-wrap gap-2">
                        @if ($subscription->onGracePeriod())
                            <flux:button variant="primary" wire:click="keepSubscription">{{ __('Keep my subscription') }}</flux:button>
                        @else
                            <flux:button wire:click="updatePaymentMethod">{{ __('Update payment method') }}</flux:button>

                            <flux:modal.trigger name="confirm-cancel">
                                <flux:button variant="ghost">{{ __('Cancel subscription') }}</flux:button>
                            </flux:modal.trigger>
                        @endif
                    </div>
                </div>

                <flux:modal name="confirm-cancel" class="max-w-lg">
                    <div class="space-y-6">
                        <div>
                            <flux:heading size="lg">{{ __('Cancel your subscription?') }}</flux:heading>
                            <flux:subheading>
                                {{ __('You keep the cloud plan until the end of your trial or the period you\'ve paid for. After that, you see your last :days days, and older logs are kept until you subscribe again.', ['days' => \App\Models\User::FREE_HISTORY_DAYS]) }}
                            </flux:subheading>
                        </div>

                        <div class="flex justify-end gap-2">
                            <flux:modal.close>
                                <flux:button variant="filled">{{ __('Keep subscription') }}</flux:button>
                            </flux:modal.close>

                            <flux:button variant="danger" wire:click="cancel">{{ __('Cancel subscription') }}</flux:button>
                        </div>
                    </div>
                </flux:modal>
            @elseif ($user->hasCloudPlan())
                <div class="{{ $card }}" data-test="plan-granted">
                    <p class="font-semibold text-ink">{{ __('Cloud plan') }}</p>
                    <p class="mt-1 text-sm text-ink-muted">{{ __('Your account has the cloud plan without a subscription, so there\'s nothing to pay.') }}</p>
                </div>
            @else
                <div data-test="plan-free">
                    <p class="text-sm text-ink-muted">
                        {{ __('You\'re on the free plan, which shows your last :days days of logs. The cloud plan shows your whole history, and older logs come back as soon as you subscribe.', ['days' => \App\Models\User::FREE_HISTORY_DAYS]) }}
                    </p>

                    @if ($subscription?->ends_at?->isPast())
                        <p class="mt-2 text-sm text-ink-muted">{{ __('Your last subscription ended on :date.', ['date' => $subscription->ends_at->format('F j, Y')]) }}</p>
                    @endif

                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div class="{{ $card }}">
                            <p class="text-sm text-ink-muted">{{ __('Monthly') }}</p>
                            <p class="mt-1 text-2xl font-semibold text-ink">$5 <span class="text-sm font-normal text-ink-muted">{{ __('a month') }}</span></p>
                            <flux:button class="mt-4 w-full" variant="primary" wire:click="subscribe('monthly')" data-test="subscribe-monthly">
                                {{ $trialDays ? __('Start :days-day free trial', ['days' => $trialDays]) : __('Subscribe') }}
                            </flux:button>
                        </div>

                        <div class="{{ $card }}">
                            <p class="text-sm text-ink-muted">{{ __('Yearly') }}</p>
                            <p class="mt-1 text-2xl font-semibold text-ink">$50 <span class="text-sm font-normal text-ink-muted">{{ __('a year') }}</span></p>
                            <flux:button class="mt-4 w-full" variant="primary" wire:click="subscribe('yearly')" data-test="subscribe-yearly">
                                {{ $trialDays ? __('Start :days-day free trial', ['days' => $trialDays]) : __('Subscribe') }}
                            </flux:button>
                        </div>
                    </div>

                    <p class="mt-3 text-xs text-zinc-500">
                        @if ($trialDays)
                            {{ __('You won\'t be charged until the trial ends, and you can cancel before then. Payments are handled by Paddle.') }}
                        @else
                            {{ __('Payments are handled by Paddle.') }}
                        @endif
                    </p>
                </div>
            @endif
        </div>
    </x-settings.layout>
</section>
