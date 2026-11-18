<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * The Mac app's calls to the hosted Daily Updates: connecting to an account, then syncing with it.
 */
class CloudClient
{
    public function baseUrl(): string
    {
        return rtrim((string) config('services.cloud.url'), '/');
    }

    /**
     * Register a new connection. Returns its code, the secret only this app knows,
     * and the page the user approves it on.
     *
     * @return array{code: string, secret: string, approve_url: string}
     */
    public function start(): array
    {
        $secret = Str::random(64);

        $response = $this->http()->post('/api/desktop/connections', ['secret' => $secret])->throw()->json();

        return ['code' => $response['code'], 'secret' => $secret, 'approve_url' => $response['approve_url']];
    }

    /**
     * "pending", "approved" or "expired".
     */
    public function status(string $code, string $secret): string
    {
        return (string) $this->http()->post("/api/desktop/connections/{$code}/status", ['secret' => $secret])->throw()->json('status');
    }

    /**
     * Finish the connection. Returns the token the Mac syncs with from now on.
     */
    public function finish(string $code, string $secret): string
    {
        return (string) $this->http()->post("/api/desktop/connections/{$code}/finish", ['secret' => $secret])->throw()->json('token');
    }

    /**
     * Send changes and get back everything that changed in the account after the cursor.
     *
     * @param  array<string, mixed>  $payload
     * @return array{cursor: int, has_more: bool, updates: list<array<string, mixed>>, todos: list<array<string, mixed>>, deleted: list<array<string, mixed>>}
     */
    public function sync(string $url, string $token, array $payload): array
    {
        return $this->http($url)->withToken($token)->timeout(60)->post('/api/desktop/sync', $payload)->throw()->json();
    }

    /**
     * Tell the cloud this Mac stopped syncing, so its token stops working.
     */
    public function disconnect(string $url, string $token): void
    {
        $this->http($url)->withToken($token)->delete('/api/desktop/sync')->throw();
    }

    protected function http(?string $url = null): PendingRequest
    {
        return Http::baseUrl(rtrim($url ?? $this->baseUrl(), '/'))->acceptJson()->asJson()->timeout(30)
            ->retry(2, 1000, fn (\Throwable $e) => $e instanceof ConnectionException, throw: false);
    }
}
