<?php

namespace App\Models;

use App\Support\Desktop;
use App\Sync\Sync;
use Illuminate\Database\Eloquent\Model;

/**
 * A log or todo deleted on one side, so the other side deletes it too.
 *
 * In the cloud, sync_version is when it was deleted. On the Mac, sync_version is
 * the cloud version the deleted row was based on, and needs_sync marks it unsent.
 *
 * @property int $user_id
 * @property string $type
 * @property string $uuid
 * @property int|null $sync_version
 * @property bool $needs_sync
 */
class SyncTombstone extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'needs_sync' => 'boolean',
        ];
    }

    /**
     * Note that a synced row was deleted.
     */
    public static function record(DailyUpdate|Todo $model): void
    {
        if (Desktop::running()) {
            // Never sent to the cloud, so there's nothing to delete there.
            if ($model->sync_version === null) {
                return;
            }

            self::put($model->user_id, $model::syncType(), $model->uuid, ['sync_version' => $model->sync_version, 'needs_sync' => true]);

            return;
        }

        self::put($model->user_id, $model::syncType(), $model->uuid, ['sync_version' => Sync::nextVersion($model->user_id)]);
    }

    /**
     * Cloud: note that a uuid the Mac sent is gone, such as a log merged into another one for the same day.
     */
    public static function recordInCloud(int $userId, string $type, string $uuid): void
    {
        self::put($userId, $type, $uuid, ['sync_version' => Sync::nextVersion($userId)]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected static function put(int $userId, string $type, string $uuid, array $attributes): void
    {
        $tombstone = self::query()->where(['user_id' => $userId, 'type' => $type, 'uuid' => $uuid])->first() ?? new self;
        $tombstone->forceFill(['user_id' => $userId, 'type' => $type, 'uuid' => $uuid, ...$attributes])->save();
    }
}
