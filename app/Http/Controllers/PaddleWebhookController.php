<?php

namespace App\Http\Controllers;

use Closure;
use Illuminate\Http\Request;
use Laravel\Paddle\Http\Controllers\WebhookController;
use Laravel\Paddle\Http\Middleware\VerifyWebhookSignature;
use Symfony\Component\HttpFoundation\Response;

/**
 * Receives Paddle's webhooks, the way Cashier does, except that the signature is
 * always checked. Cashier's own route skips the check when PADDLE_WEBHOOK_SECRET
 * is missing, which would let anyone fake a subscription; this one refuses every
 * webhook until the secret is set.
 */
class PaddleWebhookController extends WebhookController
{
    public function __construct()
    {
        // Cashier's constructor adds the signature check only when a secret is set,
        // so it isn't called here; both steps are added unconditionally instead.
        $this->middleware(function (Request $request, Closure $next): Response {
            abort_if(blank(config('cashier.webhook_secret')), 503, 'Paddle webhooks need PADDLE_WEBHOOK_SECRET.');

            return $next($request);
        });

        $this->middleware(VerifyWebhookSignature::class);
    }
}
