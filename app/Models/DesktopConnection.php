<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One "Move to cloud" from the Mac app.
 *
 * @property int $id
 * @property string $code
 * @property string $secret_hash
 * @property int|null $user_id
 * @property Carbon|null $approved_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $login_used_at
 * @property Carbon $expires_at
 * @property-read User|null $user
 */
class DesktopConnection extends Model
{
    /**
     * How long the user has to approve a new connection in their browser.
     */
    public const APPROVAL_MINUTES = 15;

    /**
     * How long the Mac app has to upload and finish once it's approved.
     */
    public const UPLOAD_MINUTES = 30;

    /**
     * How long the one-time sign-in link for the app's window stays valid.
     */
    public const LOGIN_LINK_MINUTES = 5;

    /**
     * @var list<string>
     */
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'finished_at' => 'datetime',
            'login_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Start a connection for the given secret, which only the Mac app knows.
     */
    public static function start(string $secret): self
    {
        $connection = new self;
        $connection->forceFill([
            'code' => Str::lower(Str::random(40)),
            'secret_hash' => self::hashSecret($secret),
            'expires_at' => now()->addMinutes(self::APPROVAL_MINUTES),
        ])->save();

        return $connection;
    }

    public static function hashSecret(string $secret): string
    {
        return hash('sha256', $secret);
    }

    /**
     * Whether the given secret is the one this connection was started with.
     */
    public function hasSecret(string $secret): bool
    {
        return hash_equals($this->secret_hash, self::hashSecret($secret));
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null && $this->user_id !== null;
    }

    public function isFinished(): bool
    {
        return $this->finished_at !== null;
    }

    /**
     * Approve the connection for a user, giving the app time to upload.
     */
    public function approveFor(User $user): void
    {
        $this->forceFill([
            'user_id' => $user->id,
            'approved_at' => now(),
            'expires_at' => now()->addMinutes(self::UPLOAD_MINUTES),
        ])->save();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
