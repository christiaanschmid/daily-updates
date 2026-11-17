<?php

namespace App\Http\Controllers\Api;

use App\Models\DailyUpdate;
use App\Models\DesktopConnection;
use App\Models\Todo;
use App\Support\CloudImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * What the Mac app calls to move its logs to the cloud. The approval link it
 * opens in the browser only carries the connection's code; everything here also
 * needs the secret the app started with, so the link alone can't do anything.
 */
class DesktopConnectionController
{
    /**
     * The most logs or todos one upload request may carry. The app sends more in batches.
     */
    public const MAX_PER_REQUEST = 200;

    /**
     * Start a connection and return the page the user approves it on.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(['secret' => ['required', 'string', 'min:40', 'max:255']]);

        $connection = DesktopConnection::start($validated['secret']);

        return response()->json([
            'code' => $connection->code,
            'approve_url' => route('desktop.connect', $connection->code),
            'expires_at' => $connection->expires_at->toIso8601String(),
        ], 201);
    }

    /**
     * Whether the user has approved the connection yet.
     */
    public function status(Request $request, string $code): JsonResponse
    {
        $connection = $this->connection($request, $code);

        return response()->json([
            'status' => match (true) {
                $connection->isExpired() => 'expired',
                $connection->isApproved() => 'approved',
                default => 'pending',
            },
        ]);
    }

    /**
     * Import a batch of logs and todos into the approved account.
     */
    public function import(Request $request, string $code): JsonResponse
    {
        $connection = $this->usableConnection($request, $code);

        $validated = $request->validate([
            'updates' => ['present', 'array', 'max:'.self::MAX_PER_REQUEST],
            'updates.*.date' => ['required', 'date_format:Y-m-d'],
            'updates.*.done' => ['nullable', 'string', 'max:'.DailyUpdate::MAX_SECTION_LENGTH],
            'updates.*.up_next' => ['nullable', 'string', 'max:'.DailyUpdate::MAX_SECTION_LENGTH],
            'todos' => ['present', 'array', 'max:'.self::MAX_PER_REQUEST],
            'todos.*.title' => Todo::titleRules(),
            'todos.*.completed_at' => ['nullable', 'date'],
            'todos.*.created_at' => ['nullable', 'date'],
        ]);

        $import = new CloudImport($connection->user);

        return response()->json([
            'updates' => $import->updates($validated['updates']),
            'todos' => $import->todos($validated['todos']),
        ]);
    }

    /**
     * Finish the move and return a one-time link that signs the app's window in.
     */
    public function finish(Request $request, string $code): JsonResponse
    {
        $connection = $this->usableConnection($request, $code);

        $connection->forceFill(['finished_at' => now()])->save();

        return response()->json([
            'login_url' => URL::temporarySignedRoute(
                'desktop.login',
                now()->addMinutes(DesktopConnection::LOGIN_LINK_MINUTES),
                ['connection' => $connection->code],
            ),
        ]);
    }

    /**
     * The connection for this code, if the request proves its secret.
     */
    protected function connection(Request $request, string $code): DesktopConnection
    {
        $secret = (string) $request->input('secret');
        $connection = DesktopConnection::query()->where('code', $code)->first();

        abort_if($connection === null || ! $connection->hasSecret($secret), 404);

        return $connection;
    }

    /**
     * An approved connection that hasn't expired or finished, ready for uploads.
     */
    protected function usableConnection(Request $request, string $code): DesktopConnection
    {
        $connection = $this->connection($request, $code);

        abort_if($connection->isExpired(), 410, 'This connection has expired. Start again from the Mac app.');
        abort_unless($connection->isApproved(), 409, 'This connection has not been approved yet.');
        abort_if($connection->isFinished(), 409, 'This connection is already finished.');
        abort_unless($connection->user->hasCloudPlan(), 402, 'This account no longer has the cloud plan.');

        return $connection;
    }
}
