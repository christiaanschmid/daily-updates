<?php

namespace App\Livewire\Desktop;

use App\Models\DesktopConnection;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The page the Mac app opens in the browser, where the signed-in user approves
 * moving that Mac's logs into their account.
 */
#[Title('Connect your Mac')]
class Connect extends Component
{
    #[Locked]
    public string $code = '';

    public function mount(string $code): void
    {
        $this->code = $code;
    }

    /**
     * Approve the connection, so the Mac app can upload its logs to this account.
     */
    public function approve(): void
    {
        $connection = $this->connection();

        abort_unless($this->state($connection) === 'ready', 403);

        $connection->approveFor(Auth::user());
    }

    public function render(): View
    {
        $connection = $this->connection();

        return view('livewire.desktop.connect', ['state' => $this->state($connection)]);
    }

    /**
     * Where this connection stands for the signed-in user.
     */
    protected function state(DesktopConnection $connection): string
    {
        return match (true) {
            $connection->isApproved() && $connection->user_id === Auth::id() => 'approved',
            $connection->isApproved() => 'taken',
            $connection->isExpired() => 'expired',
            ! Auth::user()->hasCloudPlan() => 'needs-plan',
            default => 'ready',
        };
    }

    protected function connection(): DesktopConnection
    {
        return DesktopConnection::query()->where('code', $this->code)->firstOrFail();
    }
}
