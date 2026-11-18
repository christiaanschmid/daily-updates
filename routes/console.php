<?php

use App\Models\CloudLink;
use App\Models\User;
use App\Support\Desktop;
use App\Sync\CloudSync;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

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

Artisan::command('cloud:sync', function (CloudSync $sync) {
    if ($sync->link() === null) {
        $this->comment('This Mac isn\'t syncing with the cloud.');

        return 0;
    }

    $sync->run();

    $this->info(CloudLink::current()->last_error ?? 'Synced.');

    return 0;
})->purpose('Mac app: sync this Mac\'s logs and todos with the cloud');

// Mac app: NativePHP runs the scheduler every minute, so the Mac picks up changes made elsewhere.
Schedule::command('cloud:sync')->everyMinute()->when(fn () => Desktop::running())->withoutOverlapping();
