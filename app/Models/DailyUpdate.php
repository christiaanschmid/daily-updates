<?php

namespace App\Models;

use Database\Factories\DailyUpdateFactory;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use DOMXPath;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * @property int $id
 * @property int $user_id
 * @property Carbon $date
 * @property string|null $done
 * @property string|null $up_next
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['date', 'done', 'up_next'])]
class DailyUpdate extends Model
{
    /** @use HasFactory<DailyUpdateFactory> */
    use HasFactory;

    /**
     * The sections of an update, keyed by attribute, with their display headings.
     * An update is a log of the workday it is dated.
     *
     * @var array<string, string>
     */
    public const SECTIONS = [
        'done' => 'Done',
        // Up next is hidden for now. Its column and saved notes are kept.
        // 'up_next' => 'Up next',
    ];

    /**
     * The headings each section is shared under at the next workday's huddle.
     *
     * @var array<string, string>
     */
    public const HUDDLE_HEADINGS = [
        'done' => 'Yesterday',
        // 'up_next' => 'Today',
    ];

    /**
     * The heading the todos checked off on the update's day are listed under.
     */
    public const COMPLETED_TODOS_HEADING = 'Completed todos';

    /**
     * The longest section the sanitizer will accept, in characters of HTML.
     */
    public const MAX_SECTION_LENGTH = 50000;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    /**
     * Get the user that wrote the update.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Clean the HTML of the "done" section before it is stored.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function done(): Attribute
    {
        return Attribute::set(fn (?string $html) => self::sanitize($html));
    }

    /**
     * Clean the HTML of the "up next" section before it is stored.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function upNext(): Attribute
    {
        return Attribute::set(fn (?string $html) => self::sanitize($html));
    }

    /**
     * Strip anything unsafe from editor HTML, storing empty sections as null.
     */
    public static function sanitize(?string $html): ?string
    {
        if ($html === null || trim(strip_tags($html)) === '') {
            return null;
        }

        $config = (new HtmlSanitizerConfig)
            ->allowSafeElements()
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->withMaxInputLength(self::MAX_SECTION_LENGTH);

        $html = self::removeEmptyListItems((new HtmlSanitizer($config))->sanitize($html));

        return trim(strip_tags($html)) === '' ? null : $html;
    }

    /**
     * Drop bullets with no text, along with any list left without bullets.
     */
    protected static function removeEmptyListItems(string $html): string
    {
        if (! str_contains($html, '<li')) {
            return $html;
        }

        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="utf-8"?><body>'.$html.'</body>', LIBXML_NOERROR | LIBXML_NOWARNING);

        $body = $document->getElementsByTagName('body')->item(0);

        if (! $body) {
            return $html;
        }

        $items = iterator_to_array($document->getElementsByTagName('li'));

        foreach (array_reverse($items) as $item) {
            if (preg_replace('/[\s\x{00A0}]+/u', '', $item->textContent) === '') {
                $item->parentNode?->removeChild($item);
            }
        }

        foreach (['ul', 'ol'] as $tag) {
            foreach (array_reverse(iterator_to_array($document->getElementsByTagName($tag))) as $list) {
                if ($list->getElementsByTagName('li')->length === 0) {
                    $list->parentNode?->removeChild($list);
                }
            }
        }

        $cleaned = '';

        foreach ($body->childNodes as $child) {
            $cleaned .= $document->saveHTML($child);
        }

        return $cleaned;
    }

    /**
     * Get a section's HTML with every case-insensitive match of the term wrapped in <mark>.
     * Only text is searched, so tags and attributes are never altered.
     */
    public function highlighted(string $section, ?string $term): string
    {
        $html = (string) $this->{$section};
        $term = trim((string) $term);

        if ($term === '' || $html === '' || mb_stripos(html_entity_decode(strip_tags($html)), $term) === false) {
            return $html;
        }

        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="utf-8"?><body>'.$html.'</body>', LIBXML_NOERROR | LIBXML_NOWARNING);

        $body = $document->getElementsByTagName('body')->item(0);

        if (! $body) {
            return $html;
        }

        $pattern = '/('.preg_quote($term, '/').')/iu';
        $textNodes = iterator_to_array((new DOMXPath($document))->query('.//text()', $body) ?: []);

        foreach ($textNodes as $textNode) {
            if (! $textNode instanceof DOMText) {
                continue;
            }

            $parts = preg_split($pattern, $textNode->textContent, -1, PREG_SPLIT_DELIM_CAPTURE);

            if ($parts === false || count($parts) === 1) {
                continue;
            }

            $fragment = $document->createDocumentFragment();

            foreach ($parts as $index => $part) {
                if ($index % 2 === 1) {
                    $fragment->appendChild($document->createElement('mark'))->appendChild($document->createTextNode($part));
                } elseif ($part !== '') {
                    $fragment->appendChild($document->createTextNode($part));
                }
            }

            $textNode->parentNode?->replaceChild($fragment, $textNode);
        }

        $highlighted = '';

        foreach ($body->childNodes as $child) {
            $highlighted .= $document->saveHTML($child);
        }

        return $highlighted;
    }

    /**
     * Get the todos the user checked off on the update's day, in the order they were done.
     *
     * @return Collection<int, Todo>
     */
    public function completedTodos(): Collection
    {
        return Todo::query()->where('user_id', $this->user_id)->completedOn($this->date)->get();
    }

    /**
     * Get the update as plain text, one heading per section.
     */
    public function toPlainText(): string
    {
        return $this->toText(self::SECTIONS);
    }

    /**
     * Get the update as plain text the way it is shared at the next workday's huddle.
     */
    public function toHuddleText(): string
    {
        return $this->toText(self::HUDDLE_HEADINGS);
    }

    /**
     * Get the sections as plain text under the given headings, followed by the day's
     * completed todos when there are any.
     *
     * @param  array<string, string>  $headings
     */
    protected function toText(array $headings): string
    {
        $blocks = collect($headings)
            ->map(fn (string $heading, string $section) => $heading.":\n".(self::htmlToText($this->{$section}) ?: '-'));

        $completedTodos = $this->completedTodos();

        if ($completedTodos->isNotEmpty()) {
            $blocks->push(self::COMPLETED_TODOS_HEADING.":\n".$completedTodos->map(fn (Todo $todo) => '- '.$todo->title)->implode("\n"));
        }

        return $blocks->implode("\n\n");
    }

    /**
     * Convert a section's HTML into indented "- " bullet lines.
     */
    protected static function htmlToText(?string $html): string
    {
        if (blank($html)) {
            return '';
        }

        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="utf-8"?><body>'.$html.'</body>', LIBXML_NOERROR | LIBXML_NOWARNING);

        $body = $document->getElementsByTagName('body')->item(0);

        return $body ? implode("\n", self::textLines($body, 0)) : '';
    }

    /**
     * Collect the text lines of a node, indenting nested list items.
     *
     * @return list<string>
     */
    protected static function textLines(DOMNode $node, int $depth): array
    {
        $lines = [];

        foreach ($node->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                if (trim($child->textContent) !== '') {
                    $lines[] = trim($child->textContent);
                }

                continue;
            }

            if (in_array($child->tagName, ['ul', 'ol'], true)) {
                foreach ($child->childNodes as $item) {
                    if ($item instanceof DOMElement && $item->tagName === 'li') {
                        $lines = [...$lines, ...self::listItemLines($item, $depth)];
                    }
                }

                continue;
            }

            if (in_array($child->tagName, ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'pre'], true)) {
                if (trim($child->textContent) !== '') {
                    $lines[] = trim($child->textContent);
                }

                continue;
            }

            $lines = [...$lines, ...self::textLines($child, $depth)];
        }

        return $lines;
    }

    /**
     * Turn a list item into a bullet line followed by its nested lists, one level deeper.
     *
     * @return list<string>
     */
    protected static function listItemLines(DOMElement $item, int $depth): array
    {
        $text = '';
        $nested = [];

        foreach ($item->childNodes as $child) {
            if ($child instanceof DOMElement && in_array($child->tagName, ['ul', 'ol'], true)) {
                $wrapper = $child->ownerDocument->createElement('div');
                $wrapper->appendChild($child->cloneNode(true));
                $nested = [...$nested, ...self::textLines($wrapper, $depth + 1)];
            } else {
                $text .= ' '.$child->textContent;
            }
        }

        return [str_repeat('  ', $depth).'- '.trim(preg_replace('/\s+/', ' ', $text)), ...$nested];
    }
}
