<?php

namespace App\Providers;

use App\Events\UseLocalDataRequested;
use App\Http\Controllers\PaddleWebhookController;
use App\Support\CloudMode;
use App\Support\Desktop;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Paddle\Cashier;
use Native\Desktop\Facades\App as NativeApp;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Paddle's webhooks go to PaddleWebhookController instead, which always checks signatures.
        Cashier::ignoreRoutes();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Blade::if('desktop', fn (): bool => Desktop::running());

        // Where Cashier's own route would be, without the web middleware: Paddle sends no session or CSRF token.
        Route::post(config('cashier.path').'/webhook', PaddleWebhookController::class)->name('cashier.webhook');

        // The Mac app's Cloud menu: go back to the logs kept on this Mac, by relaunching without the cloud.
        Event::listen(UseLocalDataRequested::class, function () {
            app(CloudMode::class)->disable();
            NativeApp::relaunch();
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
