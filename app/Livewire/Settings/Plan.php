<?php

namespace App\Livewire\Settings;

use App\Support\Desktop;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Laravel\Paddle\Subscription;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The cloud plan: subscribe through Paddle's checkout, or see and manage a
 * subscription. The desktop app keeps everything on the Mac, so it has no plan.
 *
 * @property-read Subscription|null $subscription
 * @property-read string|null $billingPeriod
 */
#[Title('Plan')]
class Plan extends Component
{
    /**
     * Set when Paddle's checkout sends the user back after paying, while its
     * webhook may still be on the way.
     */
    #[Url(except: '')]
    public string $checkout = '';

    public function mount(): void
    {
        abort_if(Desktop::running(), 404);
    }

    /**
     * Open Paddle's checkout for the monthly or yearly price.
     */
    public function subscribe(string $period): void
    {
        $priceId = config("services.paddle.prices.{$period}");

        abort_unless(in_array($period, ['monthly', 'yearly'], true) && filled($priceId), 404);

        if (Auth::user()->hasCloudPlan()) {
            return;
        }

        $checkout = Auth::user()
            ->subscribe($priceId)
            ->returnTo(route('plan.edit', ['checkout' => 'done']));

        // Cashier's options are for an inline checkout; this page opens Paddle's overlay instead.
        $options = $checkout->options();
        $options['settings'] = array_filter([
            'displayMode' => 'overlay',
            'successUrl' => $options['settings']['successUrl'] ?? null,
            'allowLogout' => false,
        ], fn ($value) => $value !== null);

        $config = [
            'token' => config('cashier.client_side_token'),
            'sandbox' => (bool) config('cashier.sandbox'),
        ];

        $this->js('window.openPaddleCheckout('.json_encode($config).', '.json_encode($options).')');
    }

    /**
     * Cancel at the end of the period already paid for, or of the trial.
     */
    public function cancel(): void
    {
        $subscription = $this->subscription;

        // valid() covers a trial as well as an active subscription.
        abort_unless($subscription !== null && $subscription->valid() && ! $subscription->onGracePeriod(), 404);

        $subscription->cancel();

        Flux::modal('confirm-cancel')->close();
        Flux::toast(text: __('Your subscription is canceled. You keep the cloud plan until :date.', ['date' => $subscription->ends_at?->format('F j, Y')]));
    }

    /**
     * Undo a cancellation before the subscription ends.
     */
    public function keepSubscription(): void
    {
        $subscription = $this->subscription;

        abort_unless($subscription?->onGracePeriod(), 404);

        $subscription->stopCancelation();

        Flux::toast(variant: 'success', text: __('Your subscription continues.'));
    }

    /**
     * Send the user to Paddle to change the card or other payment method.
     */
    public function updatePaymentMethod(): void
    {
        $subscription = $this->subscription;

        abort_unless($subscription !== null && ($subscription->valid() || $subscription->pastDue()), 404);

        $this->redirect($subscription->paymentMethodUpdateUrl());
    }

    /**
     * The user's subscription, including a canceled or failed one.
     */
    #[Computed]
    public function subscription(): ?Subscription
    {
        return Auth::user()->subscription();
    }

    /**
     * "monthly" or "yearly", from the subscription's price.
     */
    #[Computed]
    public function billingPeriod(): ?string
    {
        $subscription = $this->subscription;

        foreach (['monthly', 'yearly'] as $period) {
            if ($subscription?->hasPrice(config("services.paddle.prices.{$period}"))) {
                return $period;
            }
        }

        return null;
    }

    public function render(): View
    {
        return view('livewire.settings.plan');
    }
}
