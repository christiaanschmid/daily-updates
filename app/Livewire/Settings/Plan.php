<?php

namespace App\Livewire\Settings;

use App\Events\CloudSynced;
use App\Models\CloudLink;
use App\Support\CloudClient;
use App\Support\Desktop;
use App\Sync\CloudSync;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Paddle\Subscription;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Native\Desktop\Facades\Shell;
use Throwable;

/**
 * The cloud plan: subscribe through Paddle's checkout, or see and manage a
 * subscription. In the Mac app, this is where it starts and stops syncing with the cloud.
 *
 * @property-read Subscription|null $subscription
 * @property-read string|null $billingPeriod
 * @property-read CloudLink|null $cloudLink
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
     * Mac app, while connecting to a cloud account: "waiting" for approval, or an error to show.
     */
    public string $connectStatus = '';

    /**
     * Session key holding the connection's code and secret, kept out of the page.
     */
    protected const CONNECT_SESSION_KEY = 'cloud_connect';

    /**
     * Mac app: start connecting to a cloud account. Opens the approval page in the browser.
     */
    public function turnOnSync(CloudClient $cloud): void
    {
        abort_unless(Desktop::running(), 404);

        try {
            $connection = $cloud->start();
        } catch (Throwable $e) {
            Log::warning('Could not start connecting to the cloud', ['exception' => $e]);
            $this->connectStatus = __('Couldn\'t reach :host. Check your internet connection and try again.', ['host' => parse_url($cloud->baseUrl(), PHP_URL_HOST)]);

            return;
        }

        session([self::CONNECT_SESSION_KEY => $connection]);
        $this->connectStatus = 'waiting';

        Shell::openExternal($connection['approve_url']);
    }

    /**
     * Mac app: open the approval page again, in case the browser tab was closed.
     */
    public function openApprovalPage(): void
    {
        $connection = session(self::CONNECT_SESSION_KEY);

        if (is_array($connection)) {
            Shell::openExternal($connection['approve_url']);
        }
    }

    /**
     * Mac app: called every few seconds while waiting. Once approved, this Mac
     * starts syncing, beginning with everything on it.
     */
    public function checkConnection(CloudClient $cloud, CloudSync $sync): void
    {
        $connection = session(self::CONNECT_SESSION_KEY);

        if (! Desktop::running() || ! is_array($connection) || $this->connectStatus !== 'waiting') {
            return;
        }

        try {
            $status = $cloud->status($connection['code'], $connection['secret']);

            if ($status === 'expired') {
                $this->cancelConnecting();
                $this->connectStatus = __('The approval link expired. Click Turn on sync to try again.');

                return;
            }

            if ($status !== 'approved') {
                return;
            }

            $token = $cloud->finish($connection['code'], $connection['secret']);
        } catch (RequestException $e) {
            Log::warning('Connecting to the cloud failed', ['exception' => $e]);
            $this->cancelConnecting();
            $this->connectStatus = $e->response->json('message') ?: __('Connecting to the cloud failed. Try again.');

            return;
        } catch (Throwable $e) {
            // Probably a dropped connection; keep waiting and try again on the next check.
            Log::warning('Checking the connection to the cloud failed', ['exception' => $e]);

            return;
        }

        session()->forget(self::CONNECT_SESSION_KEY);
        $this->connectStatus = '';

        $sync->connect($cloud->baseUrl(), $token);
        $sync->run();

        unset($this->cloudLink);
    }

    /**
     * Mac app: stop waiting for approval.
     */
    public function cancelConnecting(): void
    {
        session()->forget(self::CONNECT_SESSION_KEY);
        $this->connectStatus = '';
    }

    /**
     * Mac app: sync right away.
     */
    public function syncNow(CloudSync $sync): void
    {
        abort_unless(Desktop::running(), 404);

        $sync->run();

        unset($this->cloudLink);
    }

    /**
     * Mac app: stop syncing. Everything stays on this Mac.
     */
    public function turnOffSync(CloudSync $sync): void
    {
        abort_unless(Desktop::running(), 404);

        $sync->disconnect();

        unset($this->cloudLink);

        Flux::toast(text: __('Sync is off. Your logs stay on this Mac.'));
    }

    /**
     * Mac app: the cloud account it syncs with, if any.
     */
    #[Computed]
    public function cloudLink(): ?CloudLink
    {
        return Desktop::running() ? CloudLink::current() : null;
    }

    /**
     * Mac app: show the latest sync after one in the background.
     */
    #[On('native:'.CloudSynced::class)]
    public function refreshSync(): void
    {
        unset($this->cloudLink);
    }

    /**
     * Open Paddle's checkout for the monthly or yearly price.
     */
    public function subscribe(string $period): void
    {
        // Billing is on the web; the Mac app's Plan page only turns sync on and off.
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
        // Billing is on the web; the Mac app's Plan page only turns sync on and off.
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
        // Billing is on the web; the Mac app's Plan page only turns sync on and off.
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
        // Billing is on the web; the Mac app's Plan page only turns sync on and off.
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
