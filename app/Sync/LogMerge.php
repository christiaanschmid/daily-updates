<?php

namespace App\Sync;

use App\Models\DailyUpdate;
use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Support\Collection;

/**
 * Combines two versions of the same day's log so nothing written on either side is
 * lost.
 *
 * With only the two versions, as in sync: everything in the first stays as it is,
 * and whatever the second adds goes below it. A bullet added to a list on either
 * side joins that list, rather than the whole list being repeated.
 *
 * With the version both started from too, as in an open editor: each side's
 * changes since then are applied, bullet by bullet, so an edited bullet replaces
 * the old one where it was and a deleted one stays deleted. Where both sides
 * changed the same bullets differently, both are kept, the other side's first.
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
     * Add the incoming notes to a log, below what it already has. Leaves the log unsaved.
     *
     * @param  array<string, mixed>  $incoming
     */
    public static function into(DailyUpdate $log, array $incoming): void
    {
        foreach (self::sections($incoming) as $section => $html) {
            $combined = self::html($log->{$section}, $html);

            if ($combined !== $log->{$section}) {
                $log->{$section} = $combined;
            }
        }
    }

    /**
     * One section's HTML from two versions: the first as it is, plus what only the second has.
     */
    public static function html(?string $first, ?string $second): ?string
    {
        if (blank(strip_tags((string) $second)) || str_contains((string) $first, (string) $second)) {
            return $first;
        }

        if (blank(strip_tags((string) $first))) {
            return $second;
        }

        $document = new DOMDocument;
        $body = self::body($document, (string) $first);
        $incoming = self::body($document, (string) $second);

        if ($body === null || $incoming === null) {
            return $first.$second;
        }

        foreach (iterator_to_array($incoming->childNodes) as $node) {
            $html = $document->saveHTML($node);

            if ($html !== false && self::childrenHtml($document, $body)->contains($html)) {
                continue;
            }

            $list = $node instanceof DOMElement && in_array($node->tagName, ['ul', 'ol'], true)
                ? self::lastChildList($body, $node->tagName)
                : null;

            if ($list === null) {
                $body->appendChild($node);

                continue;
            }

            // The same kind of list on both sides: add its new bullets to the existing one.
            foreach (iterator_to_array($node->childNodes) as $item) {
                $itemHtml = $document->saveHTML($item);

                if ($itemHtml === false || ! self::childrenHtml($document, $list)->contains($itemHtml)) {
                    $list->appendChild($item);
                }
            }
        }

        return self::childrenHtml($document, $body)->implode('');
    }

    /**
     * One section's HTML from the version both sides started from, this side's
     * version and the other side's.
     */
    public static function threeWay(?string $base, ?string $mine, ?string $theirs): ?string
    {
        if ($mine === $base || $mine === $theirs) {
            return $theirs;
        }

        if ($theirs === $base) {
            return $mine;
        }

        $merged = self::diff3(self::units($base), self::units($mine), self::units($theirs));

        return $merged === [] ? null : self::fromUnits($merged);
    }

    /**
     * A section as a list of units: each bullet of a top-level list, and each other
     * top-level block, as its HTML. A bullet's unit starts with its list's tag, so
     * the lists can be rebuilt.
     *
     * @return list<string>
     */
    protected static function units(?string $html): array
    {
        if (blank(strip_tags((string) $html))) {
            return [];
        }

        $document = new DOMDocument;
        $body = self::body($document, (string) $html);
        $units = [];

        foreach ($body->childNodes ?? [] as $node) {
            if ($node instanceof DOMElement && in_array($node->tagName, ['ul', 'ol'], true)) {
                foreach ($node->childNodes as $item) {
                    if ($item instanceof DOMElement && $item->tagName === 'li') {
                        $units[] = $node->tagName.':'.$document->saveHTML($item);
                    }
                }

                continue;
            }

            $units[] = ':'.$document->saveHTML($node);
        }

        return $units;
    }

    /**
     * @param  list<string>  $units
     */
    protected static function fromUnits(array $units): string
    {
        $html = '';
        $openList = null;

        foreach ($units as $unit) {
            [$list, $unitHtml] = explode(':', $unit, 2);
            $list = $list === '' ? null : $list;

            if ($list !== $openList) {
                $html .= $openList !== null ? "</{$openList}>" : '';
                $html .= $list !== null ? "<{$list}>" : '';
                $openList = $list;
            }

            $html .= $unitHtml;
        }

        return $html.($openList !== null ? "</{$openList}>" : '');
    }

    /**
     * Three-way merge of unit lists. The parts unchanged on both sides stay in
     * place; between them, a part changed on one side takes that side's version.
     *
     * @param  list<string>  $base
     * @param  list<string>  $mine
     * @param  list<string>  $theirs
     * @return list<string>
     */
    protected static function diff3(array $base, array $mine, array $theirs): array
    {
        $inMine = self::matches($base, $mine);
        $inTheirs = self::matches($base, $theirs);
        $merged = [];
        [$b, $m, $t] = [0, 0, 0];

        while (true) {
            // The next unit of the base that both sides still have.
            $stable = null;

            for ($i = $b; $i < count($base); $i++) {
                if (isset($inMine[$i], $inTheirs[$i])) {
                    $stable = $i;

                    break;
                }
            }

            $mineEnd = $stable === null ? count($mine) : $inMine[$stable];
            $theirsEnd = $stable === null ? count($theirs) : $inTheirs[$stable];

            $merged = [...$merged, ...self::resolve(
                array_slice($base, $b, ($stable ?? count($base)) - $b),
                array_slice($mine, $m, $mineEnd - $m),
                array_slice($theirs, $t, $theirsEnd - $t),
            )];

            if ($stable === null) {
                return $merged;
            }

            $merged[] = $base[$stable];
            [$b, $m, $t] = [$stable + 1, $mineEnd + 1, $theirsEnd + 1];
        }
    }

    /**
     * One stretch between unchanged units.
     *
     * @param  list<string>  $base
     * @param  list<string>  $mine
     * @param  list<string>  $theirs
     * @return list<string>
     */
    protected static function resolve(array $base, array $mine, array $theirs): array
    {
        if ($mine === $base || $mine === $theirs) {
            return $theirs;
        }

        if ($theirs === $base) {
            return $mine;
        }

        // Both changed it: the other side's version without what this side deleted, and
        // with what this side added where its first deleted unit was, so an edited
        // bullet stays in its place; otherwise at the end. A bullet edited differently
        // on each side is kept twice.
        $added = array_values(array_filter($mine, fn (string $unit) => ! in_array($unit, $base, true) && ! in_array($unit, $theirs, true)));
        $merged = [];

        foreach ($theirs as $unit) {
            if (in_array($unit, $base, true) && ! in_array($unit, $mine, true)) {
                array_push($merged, ...$added);
                $added = [];

                continue;
            }

            $merged[] = $unit;
        }

        return [...$merged, ...$added];
    }

    /**
     * Which unit of $to each unit of $from matches, by their longest common subsequence.
     *
     * @param  list<string>  $from
     * @param  list<string>  $to
     * @return array<int, int>
     */
    protected static function matches(array $from, array $to): array
    {
        $rows = count($from);
        $columns = count($to);
        $lengths = array_fill(0, $rows + 1, array_fill(0, $columns + 1, 0));

        for ($i = $rows - 1; $i >= 0; $i--) {
            for ($j = $columns - 1; $j >= 0; $j--) {
                $lengths[$i][$j] = $from[$i] === $to[$j]
                    ? $lengths[$i + 1][$j + 1] + 1
                    : max($lengths[$i + 1][$j], $lengths[$i][$j + 1]);
            }
        }

        $matches = [];
        [$i, $j] = [0, 0];

        while ($i < $rows && $j < $columns) {
            if ($from[$i] === $to[$j]) {
                $matches[$i++] = $j++;
            } elseif ($lengths[$i + 1][$j] >= $lengths[$i][$j + 1]) {
                $i++;
            } else {
                $j++;
            }
        }

        return $matches;
    }

    protected static function body(DOMDocument $document, string $html): ?DOMElement
    {
        $parsed = new DOMDocument;
        $parsed->loadHTML('<?xml encoding="utf-8"?><body>'.$html.'</body>', LIBXML_NOERROR | LIBXML_NOWARNING);

        $body = $parsed->getElementsByTagName('body')->item(0);

        if ($body === null) {
            return null;
        }

        $wrapper = $document->createElement('div');

        foreach ($body->childNodes as $child) {
            // Whitespace between blocks isn't content, and would make equal blocks look different.
            if ($child->nodeType === XML_TEXT_NODE && trim($child->textContent) === '') {
                continue;
            }

            $wrapper->appendChild($document->importNode($child, true));
        }

        return $wrapper;
    }

    /**
     * @return Collection<int, string>
     */
    protected static function childrenHtml(DOMDocument $document, DOMNode $parent): Collection
    {
        return collect(iterator_to_array($parent->childNodes))
            ->reject(fn (DOMNode $child) => $child->nodeType === XML_TEXT_NODE && trim($child->textContent) === '')
            ->map(fn (DOMNode $child) => (string) $document->saveHTML($child))
            ->values();
    }

    protected static function lastChildList(DOMElement $parent, string $tag): ?DOMElement
    {
        $lists = collect(iterator_to_array($parent->childNodes))
            ->filter(fn (DOMNode $child) => $child instanceof DOMElement && $child->tagName === $tag);

        return $lists->last();
    }
}
