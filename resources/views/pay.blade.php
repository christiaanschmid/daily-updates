{{--
    Paddle's default payment link. Paddle sends people here with ?_ptxn=txn_...
    to pay or to update their payment method, including from its emails, and
    Paddle.js opens the checkout for that transaction by itself.
--}}
<x-layouts::auth :title="__('Payment')">
    <div class="flex flex-col gap-2 text-center">
        <flux:heading size="lg">{{ __('Secure checkout') }}</flux:heading>

        @if (request()->filled('_ptxn'))
            <flux:subheading>{{ __('Paddle\'s checkout opens in a moment. Payments for :app are handled by Paddle.', ['app' => config('app.name')]) }}</flux:subheading>
        @else
            <flux:subheading>{{ __('There\'s nothing to pay here. To manage your subscription, go to Settings, then Plan.') }}</flux:subheading>
        @endif

        <a href="{{ route('plan.edit') }}" class="mt-2 text-sm text-accent-content hover:underline">{{ __('Back to your plan') }}</a>
    </div>

    @if (request()->filled('_ptxn'))
        @paddleJS
    @endif
</x-layouts::auth>
