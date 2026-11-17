<?php

namespace App\Http\Controllers;

use App\Models\DesktopConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Signs the Mac app's window into the account it just moved its logs to. The link
 * is signed, expires within minutes, and works once.
 */
class DesktopLoginController
{
    public function __invoke(Request $request, string $connection): RedirectResponse
    {
        $user = DB::transaction(function () use ($connection) {
            $record = DesktopConnection::query()->where('code', $connection)->lockForUpdate()->first();

            abort_if($record === null || ! $record->isFinished() || $record->login_used_at !== null, 403);

            $record->forceFill(['login_used_at' => now()])->save();

            return $record->user;
        });

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
