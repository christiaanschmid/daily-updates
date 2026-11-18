<?php

namespace App\Http\Controllers\Api;

use App\Models\DesktopConnection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What the Mac app calls to connect to a cloud account for sync. The approval
 * link it opens in the browser only carries the connection's code; everything
 * here also needs the secret the app started with, so the link alone can't do
 * anything.
 */
class DesktopConnectionController
{
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
     * Finish an approved connection, giving the Mac app its sync token.
     */
    public function finish(Request $request, string $code): JsonResponse
    {
        $connection = $this->usableConnection($request, $code);

        return response()->json(['token' => $connection->issueToken()]);
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
     * An approved connection that hasn't expired or finished, ready to finish.
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
