<?php

namespace App\Sync;

use App\Models\DailyUpdate;

/**
 * Combines two versions of the same day's log so nothing written on either side is lost.
 */
class LogMerge
{
    public const SECTIONS = ['done', 'up_next'];

    /**
     * The sections of an incoming log that have something in them.
     *
     * @param  array<string, mixed>  $incoming
     * @return array<string, string>
     */
    public static function sections(array $incoming): array
    {
        $sections = [];

        foreach (self::SECTIONS as $section) {
            $html = DailyUpdate::sanitize($incoming[$section] ?? null);

            if (filled(strip_tags((string) $html))) {
                $sections[$section] = (string) $html;
            }
        }

        return $sections;
    }

    /**
     * Add the incoming notes to a log, below what it already has. Notes it already
     * contains aren't added twice. Leaves the log unsaved.
     *
     * @param  array<string, mixed>  $incoming
     */
    public static function into(DailyUpdate $log, array $incoming): void
    {
        foreach (self::sections($incoming) as $section => $html) {
            $current = (string) $log->{$section};

            if (blank(strip_tags($current))) {
                $log->{$section} = $html;
            } elseif (! str_contains($current, $html)) {
                $log->{$section} = $current.$html;
            }
        }
    }
}
