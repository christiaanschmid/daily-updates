<?php

namespace App\Sync;

use App\Models\DailyUpdate;
use App\Models\SyncTombstone;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The cloud's side of a sync with a Mac: take in the Mac's changes, then send back
 * everything that changed in the account since the Mac's cursor.
 *
 * When the same log or todo changed on both sides since the Mac last synced:
 * - an edit beats a delete;
 * - a log keeps the notes from both sides, the cloud's first;
 * - a todo keeps the cloud's title, and stays checked off if either side checked it off.
 * Every row changed this way gets a new version, so the Mac gets the result back.
 */
class ServerSync
{
    /**
     * How many changes go back to the Mac in one response, at most.
     */
    public const PAGE_SIZE = 500;

    public function __construct(protected User $user) {}

    /**
     * @param  array{cursor: int, initial?: bool, updates: list<array<string, mixed>>, todos: list<array<string, mixed>>}  $request
     * @return array<string, mixed>
     */
    public function handle(array $request): array
    {
        return DB::transaction(function () use ($request) {
            foreach ($request['updates'] as $incoming) {
                $this->pushLog($incoming);
            }

            foreach ($request['todos'] as $incoming) {
                $this->pushTodo($incoming, $request['initial'] ?? false);
            }

            return $this->changesSince($request['cursor']);
        });
    }

    /**
     * @param  array<string, mixed>  $incoming
     */
    protected function pushLog(array $incoming): void
    {
        $log = $this->user->dailyUpdates()->where('uuid', $incoming['uuid'])->first();
        $changedHere = $log !== null && $this->changedSince($log, $incoming['base_version'] ?? null);

        if ($incoming['deleted'] ?? false) {
            if ($log === null) {
                return;
            }

            $changedHere ? $log->bumpSyncVersion() : $log->delete();

            return;
        }

        if ($log !== null && $changedHere) {
            LogMerge::into($log, $incoming);
            $log->isDirty() ? $log->save() : $log->bumpSyncVersion();

            return;
        }

        $sameDay = $this->user->dailyUpdates()
            ->whereDate('date', $incoming['date'])
            ->where('uuid', '!=', $incoming['uuid'])
            ->first();

        if ($sameDay !== null) {
            // The day already has another log here: keep that one, with the Mac's notes added.
            LogMerge::into($sameDay, $incoming);
            $sameDay->isDirty() ? $sameDay->save() : $sameDay->bumpSyncVersion();

            $log !== null ? $log->delete() : SyncTombstone::recordInCloud($this->user->id, DailyUpdate::syncType(), $incoming['uuid']);

            return;
        }

        $log ??= $this->user->dailyUpdates()->make(['uuid' => $incoming['uuid']]);
        $log->forceFill(['uuid' => $incoming['uuid']])->fill([
            'date' => $incoming['date'],
            'done' => $incoming['done'] ?? null,
            'up_next' => $incoming['up_next'] ?? null,
        ]);

        if (! $log->exists && LogMerge::sections($incoming) === []) {
            return;
        }

        $log->save();
        $this->forgetTombstone(DailyUpdate::syncType(), $incoming['uuid']);
    }

    /**
     * @param  array<string, mixed>  $incoming
     * @param  bool  $initial  Whether this is the Mac's first sync with the account.
     */
    protected function pushTodo(array $incoming, bool $initial): void
    {
        $todo = $this->user->todos()->where('uuid', $incoming['uuid'])->first();
        $changedHere = $todo !== null && $this->changedSince($todo, $incoming['base_version'] ?? null);
        $completedAt = $this->wallClock($incoming['completed_at'] ?? null);

        if ($incoming['deleted'] ?? false) {
            if ($todo === null) {
                return;
            }

            $changedHere ? $todo->bumpSyncVersion() : $todo->delete();

            return;
        }

        if ($todo !== null && $changedHere) {
            $todo->completed_at ??= $completedAt;
            $todo->isDirty() ? $todo->save() : $todo->bumpSyncVersion();

            return;
        }

        if ($todo === null && $initial && $this->hasSameTodo($incoming['title'], $completedAt)) {
            // On a Mac's first sync, the same todo may already be here, such as one moved to the cloud before.
            SyncTombstone::recordInCloud($this->user->id, Todo::syncType(), $incoming['uuid']);

            return;
        }

        $todo ??= $this->user->todos()->make();
        $todo->forceFill(['uuid' => $incoming['uuid']])->fill([
            'title' => $incoming['title'],
            'completed_at' => $completedAt,
        ]);

        if (! $todo->exists && ($createdAt = $this->wallClock($incoming['created_at'] ?? null))) {
            $todo->created_at = $createdAt;
        }

        $todo->save();
        $this->forgetTombstone(Todo::syncType(), $incoming['uuid']);
    }

    /**
     * Everything in the account that changed after the cursor, oldest change first,
     * a page at a time.
     *
     * @return array<string, mixed>
     */
    protected function changesSince(int $cursor): array
    {
        $latest = Sync::currentVersion($this->user->id);
        $upTo = $latest;

        $updates = $this->page($this->user->dailyUpdates()->getQuery(), $cursor, $upTo);
        $todos = $this->page($this->user->todos()->getQuery(), $cursor, $upTo);
        $deleted = $this->page(SyncTombstone::query()->where('user_id', $this->user->id), $cursor, $upTo);

        // Rows past the end of the smallest full page wait for the next request.
        $within = fn (Model $row) => $row->getAttribute('sync_version') <= $upTo;

        return [
            'cursor' => max($upTo, $cursor),
            'has_more' => $upTo < $latest,
            'updates' => $updates->filter($within)->values()->map(fn (DailyUpdate $log) => [
                'uuid' => $log->uuid,
                'date' => $log->date->format('Y-m-d'),
                'done' => $log->done,
                'up_next' => $log->up_next,
                'version' => $log->sync_version,
            ])->all(),
            'todos' => $todos->filter($within)->values()->map(fn (Todo $todo) => [
                'uuid' => $todo->uuid,
                'title' => $todo->title,
                'completed_at' => $todo->completed_at?->format('Y-m-d H:i:s'),
                'created_at' => $todo->created_at?->format('Y-m-d H:i:s'),
                'version' => $todo->sync_version,
            ])->all(),
            'deleted' => $deleted->filter($within)->values()->map(fn (SyncTombstone $tombstone) => [
                'type' => $tombstone->type,
                'uuid' => $tombstone->uuid,
                'version' => $tombstone->sync_version,
            ])->all(),
        ];
    }

    /**
     * Up to a page of rows changed after the cursor, oldest change first. When the
     * page is full, lowers $upTo to its last version, so no later change is sent
     * before an earlier one.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Collection<int, TModel>
     */
    protected function page(Builder $query, int $cursor, int &$upTo): Collection
    {
        $rows = $query->where('sync_version', '>', $cursor)
            ->orderBy('sync_version')
            ->limit(self::PAGE_SIZE + 1)
            ->get();

        if ($rows->count() > self::PAGE_SIZE) {
            $upTo = min($upTo, (int) $rows[self::PAGE_SIZE - 1]->getAttribute('sync_version'));
        }

        return $rows;
    }

    /**
     * Whether the row changed in the cloud after the version the Mac's copy is based on.
     */
    protected function changedSince(DailyUpdate|Todo $row, ?int $baseVersion): bool
    {
        return $row->sync_version !== null && ($baseVersion === null || $row->sync_version > $baseVersion);
    }

    protected function hasSameTodo(string $title, ?Carbon $completedAt): bool
    {
        return $this->user->todos()
            ->where('title', $title)
            ->when(
                $completedAt === null,
                fn ($query) => $query->whereNull('completed_at'),
                fn ($query) => $query->where('completed_at', $completedAt->format('Y-m-d H:i:s')),
            )
            ->exists();
    }

    protected function forgetTombstone(string $type, string $uuid): void
    {
        SyncTombstone::query()->where(['user_id' => $this->user->id, 'type' => $type, 'uuid' => $uuid])->delete();
    }

    /**
     * Times travel as the user's own wall-clock time, so a todo checked off at 5 PM
     * stays on that day whichever timezone each side is in.
     */
    protected function wallClock(?string $time): ?Carbon
    {
        return $time === null ? null : Carbon::createFromFormat('Y-m-d H:i:s', $time);
    }
}
