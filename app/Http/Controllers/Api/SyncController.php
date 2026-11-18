<?php

namespace App\Http\Controllers\Api;

use App\Models\DailyUpdate;
use App\Models\DesktopConnection;
use App\Models\Todo;
use App\Sync\ServerSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The Mac app's sync: it sends its changes and gets back everything that changed
 * in the account since it last synced. It proves itself with the token it got
 * when its connection was approved.
 */
class SyncController
{
    /**
     * The most logs or todos the Mac sends in one request. It sends more in the next one.
     */
    public const MAX_PER_REQUEST = 200;

    public function sync(Request $request): JsonResponse
    {
        $connection = $this->connection($request);

        abort_unless($connection->user->hasCloudPlan(), 402, 'This account no longer has the cloud plan.');

        $validated = $request->validate([
            'cursor' => ['required', 'integer', 'min:0'],
            'initial' => ['sometimes', 'boolean'],
            'updates' => ['present', 'array', 'max:'.self::MAX_PER_REQUEST],
            'updates.*.uuid' => ['required', 'uuid'],
            'updates.*.base_version' => ['nullable', 'integer', 'min:1'],
            'updates.*.deleted' => ['sometimes', 'boolean'],
            'updates.*.date' => ['required_unless:updates.*.deleted,true', 'date_format:Y-m-d'],
            'updates.*.done' => ['nullable', 'string', 'max:'.DailyUpdate::MAX_SECTION_LENGTH],
            'updates.*.up_next' => ['nullable', 'string', 'max:'.DailyUpdate::MAX_SECTION_LENGTH],
            'todos' => ['present', 'array', 'max:'.self::MAX_PER_REQUEST],
            'todos.*.uuid' => ['required', 'uuid'],
            'todos.*.base_version' => ['nullable', 'integer', 'min:1'],
            'todos.*.deleted' => ['sometimes', 'boolean'],
            'todos.*.title' => ['required_unless:todos.*.deleted,true', 'string', 'max:'.Todo::MAX_TITLE_LENGTH],
            'todos.*.completed_at' => ['nullable', 'date_format:Y-m-d H:i:s'],
            'todos.*.created_at' => ['nullable', 'date_format:Y-m-d H:i:s'],
        ]);

        $changes = (new ServerSync($connection->user))->handle($validated);

        $connection->forceFill(['last_synced_at' => now()])->save();

        return response()->json($changes);
    }

    /**
     * Stop syncing: the Mac app's token stops working.
     */
    public function disconnect(Request $request): JsonResponse
    {
        $this->connection($request)->forceFill(['token_hash' => null])->save();

        return response()->json(['disconnected' => true]);
    }

    protected function connection(Request $request): DesktopConnection
    {
        $connection = DesktopConnection::forToken((string) $request->bearerToken());

        abort_if($connection === null || $connection->user === null, 401, 'This Mac is no longer connected. Connect it again from the Mac app.');

        return $connection;
    }
}
