<?php

namespace App\Livewire\Settings;

use App\Support\CloudClient;
use App\Support\CloudMode;
use App\Support\Desktop;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Paddle\Subscription;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Native\Desktop\Facades\Shell;
use Throwable;

/**
 * The cloud plan: subscribe through Paddle's checkout, or see and manage a
 * subscription. In the Mac app, this is where its logs move to the cloud.
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

    /**
     * The Mac app's move to the cloud, while it waits for approval: "waiting", or an error to show.
     */
    public string $moveStatus = '';

    /**
     * Session key holding the connection's code and secret, kept out of the page.
     */
    protected const MOVE_SESSION_KEY = 'move_to_cloud';

    /**
     * Mac app: start moving this Mac's logs to the cloud. Opens the approval page in the browser.
     */
    public function moveToCloud(CloudClient $cloud): void
    {
        abort_unless(Desktop::running(), 404);

        try {
            $connection = $cloud->start();
        } catch (Throwable $e) {
            Log::warning('Could not start moving to the cloud', ['exception' => $e]);
            $this->moveStatus = __('Couldn\'t reach :host. Check your internet connection and try again.', ['host' => parse_url($cloud->baseUrl(), PHP_URL_HOST)]);

            return;
        }

        session([self::MOVE_SESSION_KEY => $connection]);
        $this->moveStatus = 'waiting';

        Shell::openExternal($connection['approve_url']);
    }

    /**
     * Mac app: open the approval page again, in case the browser tab was closed.
     */
    public function openApprovalPage(): void
    {
        $connection = session(self::MOVE_SESSION_KEY);

        if (is_array($connection)) {
            Shell::openExternal($connection['approve_url']);
        }
    }

    /**
     * Mac app: called every few seconds while waiting. Once approved, uploads
     * everything, switches the app to the cloud and signs its window in.
     */
    public function checkMoveToCloud(CloudClient $cloud, CloudMode $cloudMode): void
    {
        $connection = session(self::MOVE_SESSION_KEY);

        if (! Desktop::running() || ! is_array($connection) || $this->moveStatus !== 'waiting') {
            return;
        }

        try {
            $status = $cloud->status($connection['code'], $connection['secret']);

            if ($status === 'expired') {
                $this->cancelMoveToCloud();
                $this->moveStatus = __('The approval link expired. Click Move to cloud to try again.');

                return;
            }

            if ($status !== 'approved') {
                return;
            }

            $cloud->upload(Auth::user(), $connection['code'], $connection['secret']);
            $loginUrl = $cloud->finish($connection['code'], $connection['secret']);
        } catch (RequestException $e) {
            Log::warning('Moving to the cloud failed', ['exception' => $e]);
            $this->cancelMoveToCloud();
            $this->moveStatus = $e->response->json('message') ?: __('Moving to the cloud failed. Your logs are still on this Mac. Try again.');

            return;
        } catch (Throwable $e) {
            // Probably a dropped connection; keep waiting and try again on the next check.
            Log::warning('Checking the move to the cloud failed', ['exception' => $e]);

            return;
        }

        session()->forget(self::MOVE_SESSION_KEY);
        $cloudMode->enable($cloud->baseUrl());

        $this->redirect($loginUrl);
    }

    /**
     * Mac app: stop waiting for approval.
     */
    public function cancelMoveToCloud(): void
    {
        session()->forget(self::MOVE_SESSION_KEY);
        $this->moveStatus = '';
    }

    /**
     * Open Paddle's checkout for the monthly or yearly price.
     */
    public function subscribe(string $period): void
    {
        // Billing is on the web; the Mac app's Plan page only moves its logs to the cloud.
        abort_if(Desktop::running(), 404);

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
        // Billing is on the web; the Mac app's Plan page only moves its logs to the cloud.
        abort_if(Desktop::running(), 404);

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
        // Billing is on the web; the Mac app's Plan page only moves its logs to the cloud.
        abort_if(Desktop::running(), 404);

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
        // Billing is on the web; the Mac app's Plan page only moves its logs to the cloud.
        abort_if(Desktop::running(), 404);

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
        return view(Desktop::running() ? 'livewire.settings.plan-desktop' : 'livewire.settings.plan', [
            'cloudHost' => parse_url(app(CloudClient::class)->baseUrl(), PHP_URL_HOST),
        ]);
    }
}
