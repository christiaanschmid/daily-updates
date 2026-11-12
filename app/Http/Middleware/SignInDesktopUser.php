<?php

namespace App\Http\Middleware;

use App\Support\Desktop;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SignInDesktopUser
{
    /**
     * Pages that only make sense with a login, so the desktop app has none.
     *
     * @var list<string>
     */
    private const LOGIN_ONLY_ROUTES = [
        'security.edit',
    ];

    /**
     * In the desktop app, sign the local user in so there is no login screen.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Desktop::running()) {
            return $next($request);
        }

        abort_if($request->routeIs(...self::LOGIN_ONLY_ROUTES), 404);

        if (Auth::guest()) {
            Auth::login(Desktop::user(), remember: true);
            $request->session()->regenerate();
        }

        return $next($request);
    }
}
