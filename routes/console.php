<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('cloud-plan:grant {email} {--revoke : Take the cloud plan away again}', function (string $email) {
    $user = User::query()->where('email', $email)->first();

    if ($user === null) {
        $this->error("No account uses {$email}.");

        return 1;
    }

    $user->forceFill(['cloud_plan_granted' => ! $this->option('revoke')])->save();

    $this->info($this->option('revoke')
        ? "{$email} no longer has the cloud plan."
        : "{$email} has the cloud plan, with full history.");

    return 0;
})->purpose('Give an account the cloud plan without a subscription, or take it away');
