<?php

namespace App\Sync;

use App\Events\CloudSynced;
use App\Http\Controllers\Api\SyncController;
use App\Models\CloudLink;
use App\Models\DailyUpdate;
use App\Models\SyncTombstone;
use App\Models\Todo;
use App\Models\User;
use App\Support\CloudClient;
use App\Support\Desktop;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The Mac app's side of sync. Each round sends the changes made on this Mac and
 * applies everything that changed in the cloud since the last round, until both
 * sides have nothing more to send.
 *
 * A row changed on this Mac while a round is under way keeps that change, and is
 * sent in the next round; the cloud then combines it with its own copy.
 */
class CloudSync
{
    /**
     * Rounds per sync, at most, so a sync always ends.
     */
    protected const MAX_ROUNDS = 50;

    public function __construct(protected CloudClient $client) {}

    public function link(): ?CloudLink
    {
        return CloudLink::current();
    }

    /**
     * Start syncing this Mac with a cloud account. Everything on this Mac is sent
     * in the first sync, where the cloud combines it with what it already has.
     */
    public function connect(string $url, string $token): CloudLink
    {
        return DB::transaction(function () use ($url, $token) {
            CloudLink::query()->delete();
            SyncTombstone::query()->delete();

            $user = Desktop::user();

            foreach ([$user->dailyUpdates(), $user->todos()] as $rows) {
                $rows->update(['needs_sync' => true, 'sync_version' => null]);
            }

            $link = new CloudLink;
            $link->forceFill(['url' => rtrim($url, '/'), 'token' => $token, 'cursor' => 0])->save();

            return $link;
        });
    }

    /**
     * Stop syncing. Everything stays on this Mac, and the cloud stops accepting this Mac's token.
     */
    public function disconnect(): void
    {
        $link = $this->link();

        if ($link === null) {
            return;
        }

        try {
            $this->client->disconnect($link->url, $link->token);
        } catch (Throwable $e) {
            // The token stays unused; the account can still be synced from another Mac.
            Log::info('Could not tell the cloud this Mac stopped syncing', ['exception' => $e]);
        }

        $link->delete();
    }

    /**
     * Sync now, unless a sync is already running. Returns whether it ran and finished.
     */
    public function run(): bool
    {
        $link = $this->link();

        if ($link === null) {
            return false;
        }

        $lock = Cache::lock('cloud-sync', 120);

        if (! $lock->get()) {
            return false;
        }

        try {
            $changed = $this->rounds($link);
        } catch (RequestException $e) {
            $link->forceFill(['last_error' => $this->describe($e)])->save();

            return false;
        } catch (ConnectionException) {
            $link->forceFill(['last_error' => __('Couldn\'t reach :host.', ['host' => $link->host()])])->save();

            return false;
        } finally {
            $lock->release();
        }

        $link->forceFill(['last_synced_at' => now(), 'last_error' => null])->save();

        if ($changed) {
            event(new CloudSynced);
        }

        return true;
    }

    /**
     * Returns whether anything from the cloud changed this Mac.
     */
    protected function rounds(CloudLink $link): bool
    {
        $user = Desktop::user();
        $changed = false;

        for ($round = 0; $round < self::MAX_ROUNDS; $round++) {
            $outgoing = $this->outgoing($user);

            $response = $this->client->sync($link->url, $link->token, [
                'cursor' => $link->cursor,
                'initial' => $link->last_synced_at === null,
                'updates' => $outgoing['updates'],
                'todos' => $outgoing['todos'],
            ]);

            DB::transaction(function () use ($user, $outgoing, $response, $link, &$changed) {
                $this->markSent($user, $outgoing);
                $changed = $this->apply($user, $response) || $changed;
                $link->forceFill(['cursor' => (int) $response['cursor']])->save();
            });

            if (! $response['has_more'] && ! $this->hasUnsent($user)) {
                break;
            }
        }

        return $changed;
    }

    /**
     * The changes on this Mac the cloud doesn't have yet, a batch at a time.
     *
     * @return array{updates: list<array<string, mixed>>, todos: list<array<string, mixed>>}
     */
    protected function outgoing(User $user): array
    {
        $limit = intdiv(SyncController::MAX_PER_REQUEST, 2);
        $deleted = fn (string $type) => SyncTombstone::query()
            ->where(['user_id' => $user->id, 'type' => $type, 'needs_sync' => true])
            ->limit($limit)
            ->get()
            ->map(fn (SyncTombstone $tombstone) => ['uuid' => $tombstone->uuid, 'base_version' => $tombstone->sync_version, 'deleted' => true]);

        $updates = $user->dailyUpdates()->where('needs_sync', true)->limit($limit)->get()
            ->map(fn (DailyUpdate $log) => $this->logPayload($log) + ['uuid' => $log->uuid, 'base_version' => $log->sync_version]);

        $todos = $user->todos()->where('needs_sync', true)->limit($limit)->get()
            ->map(fn (Todo $todo) => $this->todoPayload($todo) + ['uuid' => $todo->uuid, 'base_version' => $todo->sync_version]);

        return [
            'updates' => [...$updates->all(), ...$deleted(DailyUpdate::syncType())->all()],
            'todos' => [...$todos->all(), ...$deleted(Todo::syncType())->all()],
        ];
    }

    /**
     * The cloud has what was sent. A row changed again since then stays marked, for the next round.
     *
     * @param  array{updates: list<array<string, mixed>>, todos: list<array<string, mixed>>}  $outgoing
     */
    protected function markSent(User $user, array $outgoing): void
    {
        $sent = [
            DailyUpdate::syncType() => [$user->dailyUpdates(), $outgoing['updates'], $this->logPayload(...)],
            Todo::syncType() => [$user->todos(), $outgoing['todos'], $this->todoPayload(...)],
        ];

        foreach ($sent as $type => [$query, $items, $payload]) {
            foreach ($items as $item) {
                if ($item['deleted'] ?? false) {
                    SyncTombstone::query()->where(['user_id' => $user->id, 'type' => $type, 'uuid' => $item['uuid']])->delete();

                    continue;
                }

                $row = (clone $query)->where('uuid', $item['uuid'])->first();

                if ($row !== null && $payload($row) === array_intersect_key($item, $payload($row))) {
                    Sync::applying(fn () => $row->forceFill(['needs_sync' => false])->save());
                }
            }
        }
    }

    /**
     * Apply the cloud's changes. Rows with changes not yet sent are left alone; the
     * next round sends them and the cloud combines them with its copy.
     *
     * @param  array<string, mixed>  $response
     */
    protected function apply(User $user, array $response): bool
    {
        $changed = false;

        Sync::applying(function () use ($user, $response, &$changed) {
            foreach ($response['deleted'] as $deleted) {
                $query = $deleted['type'] === Todo::syncType() ? $user->todos() : $user->dailyUpdates();
                $row = $query->where('uuid', $deleted['uuid'])->first();

                if ($row !== null && ! $row->needs_sync) {
                    $row->delete();
                    $changed = true;
                }
            }

            foreach ($response['updates'] as $incoming) {
                $changed = $this->applyLog($user, $incoming) || $changed;
            }

            foreach ($response['todos'] as $incoming) {
                $changed = $this->applyTodo($user, $incoming) || $changed;
            }
        });

        return $changed;
    }

    /**
     * @param  array<string, mixed>  $incoming
     */
    protected function applyLog(User $user, array $incoming): bool
    {
        $log = $user->dailyUpdates()->where('uuid', $incoming['uuid'])->first();

        if ($log?->needs_sync || $this->deletedHere($user, DailyUpdate::syncType(), $incoming['uuid'])) {
            return false;
        }

        $sameDay = $user->dailyUpdates()->whereDate('date', $incoming['date'])->where('uuid', '!=', $incoming['uuid'])->first();

        if ($sameDay !== null) {
            // Unsent notes for the same day go to the cloud first, which combines the two.
            if ($sameDay->needs_sync) {
                return false;
            }

            $sameDay->delete();
        }

        $log ??= $user->dailyUpdates()->make();
        $log->forceFill([
            'uuid' => $incoming['uuid'],
            'date' => $incoming['date'],
            'done' => $incoming['done'],
            'up_next' => $incoming['up_next'],
            'sync_version' => $incoming['version'],
            'needs_sync' => false,
        ]);

        $changed = $log->isDirty(['date', 'done', 'up_next']) || ! $log->exists;
        $log->save();

        return $changed;
    }

    /**
     * @param  array<string, mixed>  $incoming
     */
    protected function applyTodo(User $user, array $incoming): bool
    {
        $todo = $user->todos()->where('uuid', $incoming['uuid'])->first();

        if ($todo?->needs_sync || $this->deletedHere($user, Todo::syncType(), $incoming['uuid'])) {
            return false;
        }

        $todo ??= $user->todos()->make();
        $todo->forceFill([
            'uuid' => $incoming['uuid'],
            'title' => $incoming['title'],
            'completed_at' => $this->wallClock($incoming['completed_at']),
            'sync_version' => $incoming['version'],
            'needs_sync' => false,
        ]);

        if (! $todo->exists && ($createdAt = $this->wallClock($incoming['created_at'] ?? null))) {
            $todo->created_at = $createdAt;
        }

        $changed = $todo->isDirty(['title', 'completed_at']) || ! $todo->exists;
        $todo->save();

        return $changed;
    }

    /**
     * Whether the row was deleted on this Mac and the delete isn't sent yet.
     */
    protected function deletedHere(User $user, string $type, string $uuid): bool
    {
        return SyncTombstone::query()->where(['user_id' => $user->id, 'type' => $type, 'uuid' => $uuid, 'needs_sync' => true])->exists();
    }

    protected function hasUnsent(User $user): bool
    {
        return $user->dailyUpdates()->where('needs_sync', true)->exists()
            || $user->todos()->where('needs_sync', true)->exists()
            || SyncTombstone::query()->where(['user_id' => $user->id, 'needs_sync' => true])->exists();
    }

    /**
     * @return array<string, mixed>
     */
    protected function logPayload(DailyUpdate $log): array
    {
        return ['date' => $log->date->format('Y-m-d'), 'done' => $log->done, 'up_next' => $log->up_next];
    }

    /**
     * Times go as wall-clock time; see ServerSync::wallClock().
     *
     * @return array<string, mixed>
     */
    protected function todoPayload(Todo $todo): array
    {
        return [
            'title' => $todo->title,
            'completed_at' => $todo->completed_at?->format('Y-m-d H:i:s'),
            'created_at' => $todo->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    protected function wallClock(?string $time): ?Carbon
    {
        return $time === null ? null : Carbon::createFromFormat('Y-m-d H:i:s', $time);
    }

    /**
     * What to tell the user about a refused sync.
     */
    protected function describe(RequestException $e): string
    {
        return match ($e->response->status()) {
            401 => __('This Mac is no longer connected to your account. Turn sync off and on again to reconnect.'),
            402 => __('Your account no longer has the cloud plan, so sync is paused. Your logs are still on this Mac.'),
            default => $e->response->json('message') ?: __('Sync failed. It will try again shortly.'),
        };
    }
}
