<?php

use App\Livewire\Todos\Completed;
use App\Livewire\Todos\Panel;
use App\Models\DailyUpdate;
use App\Models\Todo;
use App\Models\User;
use Livewire\Livewire;

test('a todo can be added to the list', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(Panel::class)
        ->set('title', '  Review ABC-101 PR  ')
        ->call('add')
        ->assertHasNoErrors()
        ->assertSet('title', '')
        ->assertSee('Review ABC-101 PR');

    expect($user->todos()->sole()->title)->toBe('Review ABC-101 PR');
});

test('a todo needs a title', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(Panel::class)
        ->set('title', '   ')
        ->call('add')
        ->assertHasErrors(['title' => 'required']);

    expect(Todo::count())->toBe(0);
});

test('checking off a todo moves it off the list without touching done', function () {
    $user = User::factory()->create();
    $todo = Todo::factory()->for($user)->create(['title' => 'Review ABC-101 PR']);
    DailyUpdate::factory()->for($user)->create(['date' => today(), 'done' => '<ul><li><p>Team standup</p></li></ul>']);

    $this->actingAs($user);

    Livewire::test(Panel::class)
        ->call('complete', $todo->id)
        ->assertDispatched('todos-changed')
        ->assertDontSee('Review ABC-101 PR');

    expect($todo->fresh()->completed_at)->not->toBeNull()
        ->and($user->dailyUpdates()->sole()->done)->toBe('<ul><li><p>Team standup</p></li></ul>');
});

test('the completed todos section lists only the todos checked off that day', function () {
    $user = User::factory()->create();
    Todo::factory()->for($user)->create(['title' => 'Second today', 'completed_at' => today()->setTime(11, 0)]);
    Todo::factory()->for($user)->create(['title' => 'First today', 'completed_at' => today()->setTime(9, 0)]);
    Todo::factory()->for($user)->create(['title' => 'Done yesterday', 'completed_at' => today()->subDay()->setTime(16, 0)]);
    Todo::factory()->for($user)->create(['title' => 'Still open']);

    $this->actingAs($user);

    Livewire::test(Completed::class, ['date' => today()->format('Y-m-d')])
        ->assertSeeInOrder(['First today', 'Second today'])
        ->assertDontSee('Done yesterday')
        ->assertDontSee('Still open');

    Livewire::test(Completed::class, ['date' => today()->subDay()->format('Y-m-d')])
        ->assertSee('Done yesterday')
        ->assertDontSee('First today');
});

test('a todo checked off today can be reopened', function () {
    $user = User::factory()->create();
    $todo = Todo::factory()->for($user)->completed()->create(['title' => 'Review ABC-101 PR']);

    $this->actingAs($user);

    Livewire::test(Completed::class, ['date' => today()->format('Y-m-d')])
        ->call('reopen', $todo->id)
        ->assertDispatched('todos-changed')
        ->assertDontSee('Review ABC-101 PR');

    expect($todo->fresh()->completed_at)->toBeNull();
});

test('todos checked off on an earlier day cannot be reopened', function () {
    $user = User::factory()->create();
    $todo = Todo::factory()->for($user)->create(['completed_at' => today()->subDay()->setTime(16, 0)]);

    $this->actingAs($user);

    Livewire::test(Completed::class, ['date' => today()->subDay()->format('Y-m-d')])
        ->call('reopen', $todo->id)
        ->assertForbidden();

    expect($todo->fresh()->completed_at)->not->toBeNull();
});

test('completed todos come after up next, and show on earlier days only when there are some', function () {
    $user = User::factory()->create();
    DailyUpdate::factory()->for($user)->create(['date' => today()->subDays(2)]);
    DailyUpdate::factory()->for($user)->create(['date' => today()->subDay()]);
    Todo::factory()->for($user)->create(['completed_at' => today()->subDay()->setTime(16, 0)]);

    $this->actingAs($user);

    $this->get(route('updates.show', today()->format('Y-m-d')))
        ->assertSeeInOrder(['Todos', 'Done', 'Completed todos']);
    $this->get(route('updates.show', today()->subDay()->format('Y-m-d')))
        ->assertSeeLivewire(Completed::class);
    $this->get(route('updates.show', today()->subDays(2)->format('Y-m-d')))
        ->assertDontSeeLivewire(Completed::class);
});

test('completed todos are shared at the huddle and in the copied text', function () {
    $user = User::factory()->create();
    $update = DailyUpdate::factory()->for($user)->create([
        'date' => today()->subDay(),
        'done' => '<ul><li><p>Team standup</p></li></ul>',
        'up_next' => '<ul><li><p>Deploy</p></li></ul>',
    ]);
    Todo::factory()->for($user)->create(['title' => 'Review ABC-101 PR', 'completed_at' => today()->subDay()->setTime(16, 0)]);
    Todo::factory()->for($user)->create(['title' => 'Checked off today', 'completed_at' => now()]);

    $this->actingAs($user);

    expect($update->toHuddleText())->toBe("Yesterday:\n- Team standup\n\nCompleted todos:\n- Review ABC-101 PR")
        ->and($update->toPlainText())->toBe("Done:\n- Team standup\n\nCompleted todos:\n- Review ABC-101 PR");

    $this->get(route('huddle'))
        ->assertSeeInOrder(['Team standup', 'Completed todos', 'Review ABC-101 PR'])
        ->assertDontSee('Checked off today');
});

test('todos checked off on an earlier day are not listed', function () {
    $user = User::factory()->create();
    Todo::factory()->for($user)->create(['title' => 'Older todo', 'completed_at' => today()->subDay()->setTime(16, 0)]);

    $this->actingAs($user);

    Livewire::test(Panel::class)->assertDontSee('Older todo');
});

test('a todo can be deleted', function () {
    $user = User::factory()->create();
    $todo = Todo::factory()->for($user)->create();

    $this->actingAs($user);

    Livewire::test(Panel::class)->call('delete', $todo->id);

    expect($user->todos()->exists())->toBeFalse();
});

test('another user\'s todos can be neither seen nor changed', function () {
    $todo = Todo::factory()->create(['title' => 'Someone else\'s todo']);

    $this->actingAs(User::factory()->create());

    Livewire::test(Panel::class)
        ->assertDontSee('Someone else\'s todo')
        ->call('complete', $todo->id)
        ->assertNotFound();

    expect($todo->fresh()->completed_at)->toBeNull();
});

test('the todo list only appears on today\'s update', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('updates.show', today()->format('Y-m-d')))->assertSeeLivewire(Panel::class);
    $this->get(route('updates.show', today()->subDay()->format('Y-m-d')))->assertDontSeeLivewire(Panel::class);
});

test('the todo list comes before done on today\'s update, whether editing or not', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('updates.show', today()->format('Y-m-d')))
        ->assertSeeInOrder(['Todos', 'Done']);

    $user->dailyUpdates()->create(['date' => today(), 'done' => '<ul><li><p>Shipped it</p></li></ul>']);

    $this->get(route('updates.show', today()->format('Y-m-d')))
        ->assertSeeLivewire(Panel::class)
        ->assertSeeInOrder(['Todos', 'Shipped it']);
});

test('todo titles show their inline markdown, with html escaped and unsafe links dropped', function (string $title, string $expected) {
    expect((string) Todo::factory()->make(['title' => $title])->titleHtml())->toBe($expected);
})->with([
    'code' => ['Run `php artisan migrate`', 'Run <code>php artisan migrate</code>'],
    'bold and italic' => ['**Deploy** *after* lunch', '<strong>Deploy</strong> <em>after</em> lunch'],
    'safe link' => ['Read [the docs](https://laravel.com)', 'Read <a href="https://laravel.com">the docs</a>'],
    'html is escaped' => ['Check <script>alert(1)</script>', 'Check &lt;script&gt;alert(1)&lt;/script&gt;'],
    'unsafe link' => ['[click](javascript:alert(1))', '<a>click</a>'],
]);

test('todo markdown is shown on the list', function () {
    $user = User::factory()->create();
    Todo::factory()->for($user)->create(['title' => 'Run `php artisan migrate`']);

    $this->actingAs($user);

    Livewire::test(Panel::class)->assertSeeHtml('Run <code>php artisan migrate</code>');
});

test('a todo on the list can be renamed', function () {
    $user = User::factory()->create();
    $todo = Todo::factory()->for($user)->create(['title' => 'Reveiw ABC-101']);

    $this->actingAs($user);

    Livewire::test(Panel::class)
        ->call('rename', $todo->id, '  Review `ABC-101`  ')
        ->assertHasNoErrors()
        ->assertSeeHtml('Review <code>ABC-101</code>');

    expect($todo->fresh()->title)->toBe('Review `ABC-101`');
});

test('a todo cannot be renamed to nothing or past the length limit', function (string $title) {
    $user = User::factory()->create();
    $todo = Todo::factory()->for($user)->create(['title' => 'Review ABC-101']);

    $this->actingAs($user);

    Livewire::test(Panel::class)
        ->call('rename', $todo->id, $title)
        ->assertHasErrors('title');

    expect($todo->fresh()->title)->toBe('Review ABC-101');
})->with([
    'blank' => ['   '],
    'too long' => [str_repeat('a', Todo::MAX_TITLE_LENGTH + 1)],
]);

test('another user\'s todo cannot be renamed', function () {
    $todo = Todo::factory()->create(['title' => 'Someone else\'s todo']);

    $this->actingAs(User::factory()->create());

    Livewire::test(Panel::class)
        ->call('rename', $todo->id, 'Mine now')
        ->assertNotFound();

    expect($todo->fresh()->title)->toBe('Someone else\'s todo');
});

test('a todo checked off today can be renamed, but not one from an earlier day', function () {
    $user = User::factory()->create();
    $today = Todo::factory()->for($user)->completed()->create(['title' => 'Reveiw ABC-101']);
    $earlier = Todo::factory()->for($user)->create(['title' => 'Older todo', 'completed_at' => today()->subDay()->setTime(16, 0)]);

    $this->actingAs($user);

    Livewire::test(Completed::class, ['date' => today()->format('Y-m-d')])
        ->call('rename', $today->id, 'Review ABC-101')
        ->assertHasNoErrors()
        ->assertSee('Review ABC-101');

    Livewire::test(Completed::class, ['date' => today()->subDay()->format('Y-m-d')])
        ->assertDontSee('Edit Older todo')
        ->call('rename', $earlier->id, 'Changed')
        ->assertForbidden();

    expect($today->fresh()->title)->toBe('Review ABC-101')
        ->and($earlier->fresh()->title)->toBe('Older todo');
});
