<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Mac app: the cloud account it syncs with. There is at most one.
 *
 * @property string $url
 * @property string $token
 * @property int $cursor The latest cloud version this Mac has.
 * @property Carbon|null $last_synced_at
 * @property string|null $last_error
 */
class CloudLink extends Model
{
    protected $guarded = ['*'];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'cursor' => 'integer',
            'last_synced_at' => 'datetime',
        ];
    }

    public static function current(): ?self
    {
        return self::query()->first();
    }

    public function host(): string
    {
        return (string) parse_url($this->url, PHP_URL_HOST);
    }
}
