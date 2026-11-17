<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * The Mac app's side of "Move to cloud": it talks to the hosted Daily Updates.
 */
class CloudClient
{
    /**
     * How many logs or todos go in one upload request; the server accepts up to 200.
     */
    public const BATCH_SIZE = 100;

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
     * Upload all of the user's logs and todos, in batches.
     *
     * @return array{updates: int, todos: int} How many logs and todos the cloud added or changed.
     */
    public function upload(User $user, string $code, string $secret): array
    {
        $totals = ['updates' => 0, 'todos' => 0];

        $updates = $user->dailyUpdates()->oldest('date')->get()->map(fn ($update) => [
            'date' => $update->date->format('Y-m-d'),
            'done' => $update->done,
            'up_next' => $update->up_next,
        ]);

        $todos = $user->todos()->oldest('id')->get()->map(fn ($todo) => [
            'title' => $todo->title,
            'completed_at' => $todo->completed_at?->toIso8601String(),
            'created_at' => $todo->created_at?->toIso8601String(),
        ]);

        foreach ($updates->chunk(self::BATCH_SIZE) as $batch) {
            $totals['updates'] += $this->send($code, $secret, $batch->values()->all(), [])['updates'];
        }

        foreach ($todos->chunk(self::BATCH_SIZE) as $batch) {
            $totals['todos'] += $this->send($code, $secret, [], $batch->values()->all())['todos'];
        }

        return $totals;
    }

    /**
     * Finish the move. Returns the one-time link that signs the app's window in.
     */
    public function finish(string $code, string $secret): string
    {
        return (string) $this->http()->post("/api/desktop/connections/{$code}/finish", ['secret' => $secret])->throw()->json('login_url');
    }

    /**
     * @param  array<int, array<string, mixed>>  $updates
     * @param  array<int, array<string, mixed>>  $todos
     * @return array{updates: int, todos: int}
     */
    protected function send(string $code, string $secret, array $updates, array $todos): array
    {
        $response = $this->http()
            ->post("/api/desktop/connections/{$code}/import", ['secret' => $secret, 'updates' => $updates, 'todos' => $todos])
            ->throw()
            ->json();

        return ['updates' => (int) $response['updates'], 'todos' => (int) $response['todos']];
    }

    protected function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())->acceptJson()->asJson()->timeout(30)
            ->retry(2, 1000, fn (\Throwable $e) => $e instanceof ConnectionException, throw: false);
    }
}
