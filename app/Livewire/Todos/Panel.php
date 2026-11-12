<?php

namespace App\Livewire\Todos;

use App\Models\Todo;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The running todo list shown at the top of today's update. Checked-off todos are
 * listed in the update's completed todos section instead.
 *
 * @property-read Collection<int, Todo> $openTodos
 */
class Panel extends Component
{
    public string $title = '';

    /**
     * Add a todo to the end of the list.
     */
    public function add(): void
    {
        $validated = $this->validate(['title' => Todo::titleRules()]);

        Auth::user()->todos()->create(['title' => trim($validated['title'])]);

        $this->reset('title');
    }

    /**
     * Change the title of a todo still on the list.
     */
    public function rename(int $id, string $title): void
    {
        $title = trim($title);

        Validator::make(['title' => $title], ['title' => Todo::titleRules()])->validate();

        Auth::user()->todos()->open()->findOrFail($id)->update(['title' => $title]);
    }

    /**
     * Check a todo off, moving it to today's completed todos.
     */
    public function complete(int $id): void
    {
        Auth::user()->todos()->open()->findOrFail($id)->update(['completed_at' => now()]);

        $this->dispatch('todos-changed');
    }

    /**
     * Remove a todo that is no longer needed.
     */
    public function delete(int $id): void
    {
        Auth::user()->todos()->open()->findOrFail($id)->delete();
    }

    /**
     * The todos still to do, oldest first.
     *
     * @return Collection<int, Todo>
     */
    #[Computed]
    public function openTodos(): Collection
    {
        return Auth::user()->todos()->open()->oldest()->oldest('id')->get();
    }

    /**
     * Show the list again after a todo is reopened elsewhere on the page.
     */
    #[On('todos-changed')]
    public function refreshList(): void
    {
        unset($this->openTodos);
    }

    public function render(): View
    {
        return view('livewire.todos.panel');
    }
}
