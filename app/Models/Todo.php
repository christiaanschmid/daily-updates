<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\TodoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['title', 'completed_at'])]
class Todo extends Model
{
    /** @use HasFactory<TodoFactory> */
    use HasFactory;

    /**
     * The longest a todo's title may be.
     */
    public const MAX_TITLE_LENGTH = 255;

    /**
     * The validation rules for a todo's title.
     *
     * @return array<int, string>
     */
    public static function titleRules(): array
    {
        return ['required', 'string', 'max:'.self::MAX_TITLE_LENGTH];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the title with its inline Markdown (`code`, **bold**, *italic*, links) as HTML.
     * Any HTML typed into the title is escaped and unsafe links are dropped.
     */
    public function titleHtml(): HtmlString
    {
        return new HtmlString(rtrim(Str::inlineMarkdown($this->title, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ])));
    }

    /**
     * Get the user the todo belongs to.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Limit the query to todos that have not been checked off.
     *
     * @param  Builder<Todo>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereNull('completed_at');
    }

    /**
     * Limit the query to todos checked off on the given day, in the order they were done.
     *
     * @param  Builder<Todo>  $query
     */
    #[Scope]
    protected function completedOn(Builder $query, CarbonInterface $day): void
    {
        $query->whereBetween('completed_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
            ->oldest('completed_at')
            ->oldest('id');
    }
}
