<?php

namespace App\Models\Concerns;

use App\Jobs\SyncWithCloud;
use App\Models\CloudLink;
use App\Models\SyncTombstone;
use App\Support\Desktop;
use App\Sync\Sync;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A model that syncs between the Mac app and the cloud.
 *
 * In the cloud, every change gets the account's next version, so the Mac can ask
 * for everything that changed since it last synced. On the Mac, every change is
 * marked to be sent, and a sync starts shortly after.
 *
 * @property string $uuid
 * @property int|null $sync_version
 * @property bool $needs_sync
 * @property int $user_id
 */
trait Syncs
{
    public static function bootSyncs(): void
    {
        static::creating(function (self $model) {
            $model->uuid ??= (string) Str::uuid();
        });

        static::saving(function (self $model) {
            if (Sync::isApplying() || ($model->exists && ! $model->isDirty())) {
                return;
            }

            if (Desktop::running()) {
                $model->needs_sync = true;
            } else {
                $model->sync_version = Sync::nextVersion($model->user_id);
            }
        });

        static::saved(function (self $model) {
            if ($model->wasRecentlyCreated || $model->wasChanged()) {
                self::syncSoon();
            }
        });

        static::deleted(function (self $model) {
            if (Sync::isApplying()) {
                return;
            }

            SyncTombstone::record($model);
            self::syncSoon();
        });
    }

    /**
     * The type name sent between the Mac and the cloud.
     */
    abstract public static function syncType(): string;

    /**
     * Save in a transaction, so in the cloud the account's version stays locked until the row is saved.
     *
     * @param  array<string, mixed>  $options
     */
    public function save(array $options = []): bool
    {
        return DB::transaction(fn () => parent::save($options));
    }

    public function delete(): ?bool
    {
        return DB::transaction(fn () => parent::delete());
    }

    /**
     * Cloud: give the row a new version without changing it, so every Mac gets it again.
     */
    public function bumpSyncVersion(): void
    {
        DB::transaction(function () {
            $this->sync_version = Sync::nextVersion($this->user_id);
            $this->saveQuietly();
        });
    }

    /**
     * Mac app: sync shortly after a change, when it syncs with the cloud.
     */
    protected static function syncSoon(): void
    {
        if (Sync::isApplying() || ! Desktop::running() || ! CloudLink::query()->exists()) {
            return;
        }

        SyncWithCloud::dispatch()->delay(now()->addSeconds(SyncWithCloud::DELAY_SECONDS))->afterCommit();
    }
}
