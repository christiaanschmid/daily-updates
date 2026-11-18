<?php

namespace App\Http\Controllers\Desktop;

use App\Support\Desktop;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Native\Desktop\Facades\Shell;

/**
 * Mac app: opens a link to another site in the default browser instead of the app's window.
 */
class OpenExternalLinkController
{
    public function __invoke(Request $request): Response
    {
        abort_unless(Desktop::running(), 404);

        $validated = $request->validate([
            'url' => ['required', 'string', 'max:2048', 'regex:/^(https?:\/\/|mailto:)/i'],
        ]);

        Shell::openExternal($validated['url']);

        return response()->noContent();
    }
}
