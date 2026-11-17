<?php

namespace App\Models;

use App\Support\Desktop;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Paddle\Billable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $timezone
 * @property bool $cloud_plan_granted
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'timezone', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use Billable, HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * How many days of logs, today included, a free web account can see.
     */
    public const FREE_HISTORY_DAYS = 14;

    /**
     * Defaults for new users, matching the database's.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'cloud_plan_granted' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'cloud_plan_granted' => 'boolean',
        ];
    }

    /**
     * Get the user's daily updates.
     *
     * @return HasMany<DailyUpdate, $this>
     */
    public function dailyUpdates(): HasMany
    {
        return $this->hasMany(DailyUpdate::class);
    }

    /**
     * The daily updates this user can see: all of them on the cloud plan or in the
     * desktop app, and only the recent ones on a free web account.
     *
     * @return HasMany<DailyUpdate, $this>
     */
    public function visibleUpdates(): HasMany
    {
        $start = $this->historyStartsOn();

        return $this->dailyUpdates()->when($start, fn ($query) => $query->whereDate('date', '>=', $start));
    }

    /**
     * Whether this account has the cloud plan, with its full history: a Paddle
     * subscription that is active, in its trial, or canceled but not yet ended,
     * or the plan given by hand with cloud-plan:grant.
     */
    public function hasCloudPlan(): bool
    {
        return $this->cloud_plan_granted || $this->subscribed();
    }

    /**
     * The earliest day this user can see, or null when they can see every day.
     * The desktop app keeps everything on the user's own Mac, so it has no limit.
     */
    public function historyStartsOn(): ?CarbonInterface
    {
        if (Desktop::running() || $this->hasCloudPlan()) {
            return null;
        }

        return today()->subDays(self::FREE_HISTORY_DAYS - 1);
    }

    /**
     * Whether this user can see and write the log for the given day.
     */
    public function canSeeDay(CarbonInterface $day): bool
    {
        $start = $this->historyStartsOn();

        return $start === null || $day->copy()->startOfDay()->gte($start);
    }

    /**
     * The timezone dates follow for this user: the one they chose, otherwise the
     * Mac's own in the desktop app, otherwise the app's default.
     */
    public function effectiveTimezone(): string
    {
        return $this->timezone
            ?? (Desktop::running() ? Desktop::systemTimezone() : null)
            ?? config('app.default_timezone');
    }

    /**
     * Get the user's todos.
     *
     * @return HasMany<Todo, $this>
     */
    public function todos(): HasMany
    {
        return $this->hasMany(Todo::class);
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
