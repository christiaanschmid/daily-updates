<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Updates were written as the morning huddle: "yesterday" held the previous workday's
 * accomplishments and "today" the plan for the huddle day. They are now a log of the
 * workday itself, so each one moves back a workday and its sections become "done"
 * and "up_next".
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('daily_updates', function (Blueprint $table) {
            $table->renameColumn('yesterday', 'done');
            $table->renameColumn('today', 'up_next');
        });

        $this->shiftDates(fn (Carbon $date) => $date->subWeekday(), 'asc');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->shiftDates(fn (Carbon $date) => $date->addWeekday(), 'desc');

        Schema::table('daily_updates', function (Blueprint $table) {
            $table->renameColumn('done', 'yesterday');
            $table->renameColumn('up_next', 'today');
        });
    }

    /**
     * Move every update to a new date, in an order that never collides with an update
     * that has not moved yet.
     *
     * @param  callable(Carbon): Carbon  $shift
     * @param  'asc'|'desc'  $direction
     */
    protected function shiftDates(callable $shift, string $direction): void
    {
        DB::table('daily_updates')->orderBy('date', $direction)->get(['id', 'date'])->each(
            fn (object $update) => DB::table('daily_updates')->where('id', $update->id)->update([
                'date' => $shift(Carbon::parse($update->date))->startOfDay()->toDateTimeString(),
            ])
        );
    }
};
