<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Cloud sync between the Mac app and the hosted app. Every log and todo gets a
     * uuid that is the same on both sides. In the cloud, sync_version is the
     * account's change counter when the row last changed; on the Mac, it's the
     * cloud version the row is based on, and needs_sync marks changes not yet sent.
     */
    public function up(): void
    {
        foreach (['daily_updates', 'todos'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->uuid('uuid')->nullable()->after('id');
                $table->unsignedBigInteger('sync_version')->nullable();
                $table->boolean('needs_sync')->default(false);
            });
        }

        Schema::table('users', function (Blueprint $table) {
            // The cloud's change counter for this account.
            $table->unsignedBigInteger('sync_version')->default(0);
        });

        // Logs and todos deleted on one side, so the other side deletes them too.
        Schema::create('sync_tombstones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->uuid('uuid');
            $table->unsignedBigInteger('sync_version')->nullable();
            $table->boolean('needs_sync')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'type', 'uuid']);
            $table->index(['user_id', 'sync_version']);
        });

        // Mac app: the cloud account it syncs with. At most one row.
        Schema::create('cloud_links', function (Blueprint $table) {
            $table->id();
            $table->string('url');
            $table->text('token');
            $table->unsignedBigInteger('cursor')->default(0);
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::table('desktop_connections', function (Blueprint $table) {
            // A hash of the token the Mac app syncs with, from when the connection finished.
            $table->string('token_hash', 64)->nullable()->unique();
            $table->timestamp('last_synced_at')->nullable();
            $table->dropColumn('login_used_at');
        });

        $this->backfill();

        foreach (['daily_updates', 'todos'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->unique(['user_id', 'uuid']);
                $table->index(['user_id', 'sync_version']);
            });
        }
    }

    /**
     * Give existing logs and todos a uuid and a version, counted per account.
     */
    protected function backfill(): void
    {
        $versions = [];

        foreach (['daily_updates', 'todos'] as $table) {
            DB::table($table)->orderBy('id')->select(['id', 'user_id'])->each(function (object $row) use ($table, &$versions) {
                $versions[$row->user_id] = ($versions[$row->user_id] ?? 0) + 1;

                DB::table($table)->where('id', $row->id)->update([
                    'uuid' => (string) Str::uuid(),
                    'sync_version' => $versions[$row->user_id],
                ]);
            });
        }

        foreach ($versions as $userId => $version) {
            DB::table('users')->where('id', $userId)->update(['sync_version' => $version]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('desktop_connections', function (Blueprint $table) {
            $table->dropUnique(['token_hash']);
            $table->dropColumn(['token_hash', 'last_synced_at']);
            $table->timestamp('login_used_at')->nullable();
        });

        Schema::dropIfExists('cloud_links');
        Schema::dropIfExists('sync_tombstones');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('sync_version');
        });

        foreach (['daily_updates', 'todos'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropUnique(['user_id', 'uuid']);
                $table->dropIndex(['user_id', 'sync_version']);
                $table->dropColumn(['uuid', 'sync_version', 'needs_sync']);
            });
        }
    }
};
