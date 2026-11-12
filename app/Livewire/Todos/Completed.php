<?php

namespace App\Livewire\Todos;

use App\Models\Todo;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The todos checked off on an update's day, listed below its sections. Today's can
 * be reopened, which puts them back on the todo list.
 *
 * @property-read Collection<int, Todo> $todos
 */
class Completed extends Component
{
    /**
     * The day, in Y-m-d format.
     */
    #[Locked]
    public string $date = '';

    /**
     * Put a todo checked off today back on the todo list.
     */
    public function reopen(int $id): void
    {
        abort_unless($this->isToday(), 403);

        $this->todos->findOrFail($id)->update(['completed_at' => null]);

        unset($this->todos);

        $this->dispatch('todos-changed');
    }

    /**
     * Change the title of a todo checked off today, so it reads right at the huddle.
     */
    public function rename(int $id, string $title): void
    {
        abort_unless($this->isToday(), 403);

        $title = trim($title);

        Validator::make(['title' => $title], ['title' => Todo::titleRules()])->validate();

        $this->todos->findOrFail($id)->update(['title' => $title]);

        unset($this->todos);
    }

    /**
     * Show the list again after a todo is checked off elsewhere on the page.
     */
    #[On('todos-changed')]
    public function refreshList(): void
    {
        unset($this->todos);
    }

    /**
     * The todos checked off on the day, in the order they were done.
     *
     * @return Collection<int, Todo>
     */
    #[Computed]
    public function todos(): Collection
    {
        return Auth::user()->todos()->completedOn(Carbon::createFromFormat('!Y-m-d', $this->date))->get();
    }

    /**
     * Whether the day is today, when its todos can still be reopened.
     */
    public function isToday(): bool
    {
        return $this->date === today()->format('Y-m-d');
    }

    public function render(): View
    {
        return view('livewire.todos.completed');
    }
}
