<?php

namespace App\Support;

use App\Models\DailyUpdate;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Brings logs and todos from the Mac app into a cloud account.
 *
 * Nothing in the cloud is lost: a day that already has a log keeps it, with the
 * Mac's notes added below. Importing the same data twice changes nothing, so the
 * Mac app can safely retry an upload that was cut off.
 */
class CloudImport
{
    public function __construct(protected User $user) {}

    /**
     * Import the Mac's logs.
     *
     * @param  list<array{date: string, done?: string|null, up_next?: string|null}>  $updates
     * @return int How many days were added or changed.
     */
    public function updates(array $updates): int
    {
        $changed = 0;

        DB::transaction(function () use ($updates, &$changed) {
            foreach ($updates as $incoming) {
                $existing = $this->user->dailyUpdates()->whereDate('date', $incoming['date'])->first();

                if ($existing === null) {
                    $sections = $this->sections($incoming);

                    if ($sections !== []) {
                        $this->user->dailyUpdates()->create(['date' => $incoming['date'], ...$sections]);
                        $changed++;
                    }

                    continue;
                }

                if ($this->combine($existing, $incoming)) {
                    $changed++;
                }
            }
        });

        return $changed;
    }

    /**
     * Import the Mac's todos, open and completed.
     *
     * @param  list<array{title: string, completed_at?: string|null, created_at?: string|null}>  $todos
     * @return int How many todos were added.
     */
    public function todos(array $todos): int
    {
        $added = 0;

        DB::transaction(function () use ($todos, &$added) {
            foreach ($todos as $incoming) {
                $completedAt = $this->localTime($incoming['completed_at'] ?? null);

                $alreadyThere = $this->user->todos()
                    ->where('title', $incoming['title'])
                    ->when(
                        $completedAt === null,
                        fn ($query) => $query->whereNull('completed_at'),
                        fn ($query) => $query->where('completed_at', $completedAt->format('Y-m-d H:i:s')),
                    )
                    ->exists();

                if ($alreadyThere) {
                    continue;
                }

                $todo = $this->user->todos()->make(['title' => $incoming['title'], 'completed_at' => $completedAt]);

                if ($createdAt = $this->localTime($incoming['created_at'] ?? null)) {
                    $todo->created_at = $createdAt;
                }

                $todo->save();
                $added++;
            }
        });

        return $added;
    }

    /**
     * The sections of an incoming log that have something in them.
     *
     * @param  array<string, mixed>  $incoming
     * @return array<string, string>
     */
    protected function sections(array $incoming): array
    {
        $sections = [];

        foreach (['done', 'up_next'] as $section) {
            $html = DailyUpdate::sanitize($incoming[$section] ?? null);

            if (filled(strip_tags((string) $html))) {
                $sections[$section] = (string) $html;
            }
        }

        return $sections;
    }

    /**
     * Add the Mac's notes to a cloud log for the same day. Returns whether anything changed.
     *
     * @param  array<string, mixed>  $incoming
     */
    protected function combine(DailyUpdate $existing, array $incoming): bool
    {
        foreach ($this->sections($incoming) as $section => $html) {
            $current = (string) $existing->{$section};

            if (blank(strip_tags($current))) {
                $existing->{$section} = $html;
            } elseif (! str_contains($current, $html)) {
                $existing->{$section} = $current.$html;
            }
        }

        if (! $existing->isDirty()) {
            return false;
        }

        $existing->save();

        return true;
    }

    /**
     * A time from the Mac, in this account's own timezone, the way the app stores times.
     */
    protected function localTime(?string $time): ?Carbon
    {
        return $time === null ? null : Carbon::parse($time)->setTimezone($this->user->effectiveTimezone());
    }
}
