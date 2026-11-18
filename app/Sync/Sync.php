<?php

namespace App\Sync;

use Illuminate\Support\Facades\DB;

/**
 * Shared bookkeeping for cloud sync.
 */
class Sync
{
    /**
     * Whether changes are being copied in from the other side, which aren't new changes of their own.
     */
    protected static bool $applying = false;

    /**
     * Run the callback without marking what it changes as a new change to sync.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function applying(callable $callback): mixed
    {
        $previous = self::$applying;
        self::$applying = true;

        try {
            return $callback();
        } finally {
            self::$applying = $previous;
        }
    }

    public static function isApplying(): bool
    {
        return self::$applying;
    }

    /**
     * Cloud: the account's next change version. The account's row stays locked until
     * the surrounding transaction commits, so versions become visible in order and a
     * Mac reading changes "after version N" never skips one that commits late.
     */
    public static function nextVersion(int $userId): int
    {
        return DB::transaction(function () use ($userId) {
            $version = (int) DB::table('users')->where('id', $userId)->lockForUpdate()->value('sync_version') + 1;

            DB::table('users')->where('id', $userId)->update(['sync_version' => $version]);

            return $version;
        });
    }

    /**
     * Cloud: the account's latest change version.
     */
    public static function currentVersion(int $userId): int
    {
        return (int) DB::table('users')->where('id', $userId)->value('sync_version');
    }
}
