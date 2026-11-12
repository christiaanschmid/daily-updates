<?php

use App\Livewire\Updates\Huddle;
use App\Livewire\Updates\Index;
use App\Livewire\Updates\Show;
use App\Models\DailyUpdate;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $this->get(route('updates.show', '2026-10-26'))->assertRedirect(route('login'));
});

test('a new update starts in edit mode, without the previous plan while up next is hidden', function () {
    $user = User::factory()->create();
    DailyUpdate::factory()->for($user)->create(['date' => '2026-10-21', 'up_next' => '<ul><li><p>Older plan</p></li></ul>']);
    DailyUpdate::factory()->for($user)->create(['date' => '2026-10-23', 'up_next' => '<ul><li><p>Product page redesign</p></li></ul>']);

    $this->actingAs($user);

    Livewire::test(Show::class, ['date' => '2026-10-26'])
        ->assertSet('editing', true)
        ->assertSet('done', Show::EMPTY_BULLET_LIST)
        ->assertDontSee('Up next')
        ->assertDontSee('What you planned on')
        ->assertDontSee('Product page redesign');
});

test('an update can be saved for a day', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(Show::class, ['date' => '2026-10-26'])
        ->set('done', '<ul><li><p>Finished up ABC-102</p><ul><li><p>Queued a test email</p></li></ul></li></ul>')
        ->set('up_next', '<ul><li><p>Jumping into some new cards</p></li></ul>')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('editing', false)
        ->assertSee('Finished up ABC-102');

    $update = $user->dailyUpdates()->sole();

    expect($update->date->format('Y-m-d'))->toBe('2026-10-26')
        ->and($update->done)->toBe('<ul><li><p>Finished up ABC-102</p><ul><li><p>Queued a test email</p></li></ul></li></ul>')
        ->and($update->up_next)->toBe('<ul><li><p>Jumping into some new cards</p></li></ul>');
});

test('typing in a section saves it without leaving edit mode', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $this->travelTo(today()->setTime(14, 5));

    Livewire::test(Show::class, ['date' => '2026-10-26'])
        ->set('done', '<ul><li><p>Product page redesign</p></li></ul>')
        ->assertHasNoErrors()
        ->assertSet('editing', true)
        ->assertSet('lastSavedAt', '2:05 PM')
        ->assertSee('Saved at 2:05 PM');

    expect($user->dailyUpdates()->sole()->done)->toBe('<ul><li><p>Product page redesign</p></li></ul>');
});

test('opening a new day without writing anything saves nothing', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(Show::class, ['date' => '2026-10-26'])
        ->set('done', Show::EMPTY_BULLET_LIST)
        ->call('save')
        ->assertRedirect(route('dashboard'));

    expect(DailyUpdate::count())->toBe(0);
});

test('an autosaved new update moves along when its date is changed', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Livewire::test(Show::class, ['date' => '2026-10-26'])
        ->set('done', '<ul><li><p>Backfilled plan</p></li></ul>');

    expect($user->dailyUpdates()->sole()->date->format('Y-m-d'))->toBe('2026-10-26');

    $component->set('selectedDate', '2026-10-22')
        ->assertNoRedirect()
        ->assertSet('date', '2026-10-22');

    expect($user->dailyUpdates()->sole()->date->format('Y-m-d'))->toBe('2026-10-22');
});

test('saving an existing update changes it instead of adding another', function () {
    $user = User::factory()->create();
    DailyUpdate::factory()->for($user)->create(['date' => '2026-10-26', 'done' => null]);

    $this->actingAs($user);

    Livewire::test(Show::class, ['date' => '2026-10-26'])
        ->assertSet('editing', false)
        ->call('edit')
        ->assertSet('done', Show::EMPTY_BULLET_LIST)
        ->set('done', '<p>Deploy ABC-101</p>')
        ->call('save')
        ->assertHasNoErrors();

    expect($user->dailyUpdates()->sole()->done)->toBe('<p>Deploy ABC-101</p>');
});

test('a new update can be moved to another day before it is saved', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(Show::class, ['date' => '2026-10-26'])
        ->set('done', '<ul><li><p>Backfilled plan</p></li></ul>')
        ->set('selectedDate', '2026-10-22')
        ->assertSet('date', '2026-10-22')
        ->assertSet('done', '<ul><li><p>Backfilled plan</p></li></ul>')
        ->assertNoRedirect()
        ->call('save')
        ->assertHasNoErrors();

    expect($user->dailyUpdates()->sole()->date->format('Y-m-d'))->toBe('2026-10-22');
});

test('picking a day that already has an update opens it', function () {
    $user = User::factory()->create();
    DailyUpdate::factory()->for($user)->create(['date' => '2026-10-22']);

    $this->actingAs($user);

    Livewire::test(Show::class, ['date' => '2026-10-26'])
        ->set('selectedDate', '2026-10-22')
        ->assertRedirect(route('updates.show', '2026-10-22'))
        ->assertSet('date', '2026-10-26');
});

test('picking a day from a saved update opens that day', function () {
    $user = User::factory()->create();
    DailyUpdate::factory()->for($user)->create(['date' => '2026-10-26']);

    $this->actingAs($user);

    Livewire::test(Show::class, ['date' => '2026-10-26'])
        ->set('selectedDate', '2026-10-22')
        ->assertRedirect(route('updates.show', '2026-10-22'));

    expect($user->dailyUpdates()->sole()->date->format('Y-m-d'))->toBe('2026-10-26');
});

test('picking an impossible day is ignored', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(Show::class, ['date' => '2026-10-26'])
        ->set('selectedDate', '2026-02-30')
        ->assertSet('selectedDate', '2026-10-26')
        ->assertSet('date', '2026-10-26')
        ->assertNoRedirect();
});

test('sections longer than the limit are rejected', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(Show::class, ['date' => '2026-10-26'])
        ->set('up_next', str_repeat('a', DailyUpdate::MAX_SECTION_LENGTH + 1))
        ->call('save')
        ->assertHasErrors(['up_next' => 'max']);

    expect(DailyUpdate::count())->toBe(0);
});

test('an update can be deleted', function () {
    $user = User::factory()->create();
    DailyUpdate::factory()->for($user)->create(['date' => '2026-10-26']);

    $this->actingAs($user);

    Livewire::test(Show::class, ['date' => '2026-10-26'])
        ->call('delete')
        ->assertRedirect(route('dashboard'));

    expect($user->dailyUpdates()->exists())->toBeFalse();
});

test('a user never sees another user\'s update', function () {
    DailyUpdate::factory()->create(['date' => '2026-10-26', 'done' => '<p>Someone else\'s plan</p>']);

    $this->actingAs(User::factory()->create());

    Livewire::test(Show::class, ['date' => '2026-10-26'])
        ->assertSet('editing', true)
        ->assertSet('done', Show::EMPTY_BULLET_LIST)
        ->assertDontSee('Someone else\'s plan');

    Livewire::test(Index::class)->assertDontSee('Someone else\'s plan');
});

test('dates that do not exist return not found', function (string $date) {
    $this->actingAs(User::factory()->create());

    $this->get('/updates/'.$date)->assertNotFound();
})->with(['2026-02-30', '2026-13-01', 'today']);

test('empty bullets are removed when an update is saved', function (string $html, ?string $expected) {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(Show::class, ['date' => '2026-10-26'])
        ->set('done', '<p>Keeps the update</p>')
        ->set('up_next', $html)
        ->call('save')
        ->assertHasNoErrors();

    expect($user->dailyUpdates()->sole()->up_next)->toBe($expected);
})->with([
    'trailing empty bullet' => [
        '<ul><li><p>Product page redesign</p></li><li><p></p></li></ul>',
        '<ul><li><p>Product page redesign</p></li></ul>',
    ],
    'empty nested bullet' => [
        '<ul><li><p>Testing</p><ul><li><p>OPS-302</p></li><li><p> </p></li></ul></li></ul>',
        '<ul><li><p>Testing</p><ul><li><p>OPS-302</p></li></ul></li></ul>',
    ],
    'nested list left with no bullets' => [
        '<ul><li><p>Testing</p><ul><li><p></p></li></ul></li></ul>',
        '<ul><li><p>Testing</p></li></ul>',
    ],
    'non-breaking space only' => [
        "<ol><li><p>First</p></li><li><p>\u{00A0}</p></li></ol>",
        '<ol><li><p>First</p></li></ol>',
    ],
    'only empty bullets' => [
        '<ul><li><p></p></li><li><p></p></li></ul>',
        null,
    ],
    'text around the list is kept as written' => [
        '<p>“Coming Soon” &amp; more</p><ul><li><p>Check-in w/ Sam</p></li><li><p></p></li></ul>',
        '<p>“Coming Soon” &amp; more</p><ul><li><p>Check-in w/ Sam</p></li></ul>',
    ],
]);

test('unsafe markup is removed before an update is stored', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(Show::class, ['date' => '2026-10-26'])
        ->set('done', '<p onclick="steal()">Plan <a href="javascript:alert(1)">link</a></p><script>alert(1)</script>')
        ->call('save')
        ->assertHasNoErrors();

    $done = $user->dailyUpdates()->sole()->done;

    expect($done)->toContain('Plan')
        ->not->toContain('<script')
        ->not->toContain('onclick')
        ->not->toContain('javascript:');

    $this->get(route('updates.show', '2026-10-26'))
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false);
});

test('the page title shows the saved update\'s date, or today for a new update', function () {
    $user = User::factory()->create();
    DailyUpdate::factory()->for($user)->create(['date' => '2026-10-22']);

    $this->actingAs($user);
    $this->travelTo(today()->setDate(2026, 10, 26));

    $this->get(route('updates.show', '2026-10-22'))
        ->assertSee(config('app.name').' - 10/22/2026', false)
        ->assertDontSee('10/22/2026 - '.config('app.name'), false);

    $this->get(route('updates.show', '2026-10-23'))->assertSee(config('app.name').' - 10/26/2026', false);
});

test('the history lists updates newest first and can be searched', function () {
    $user = User::factory()->create();
    DailyUpdate::factory()->for($user)->create(['date' => '2026-10-22', 'done' => '<p>Fixed OPS-301</p>']);
    DailyUpdate::factory()->for($user)->create(['date' => '2026-10-23', 'done' => '<p>Finished up ABC-102</p>']);

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->assertSeeInOrder(['Friday, October 23, 2026', 'Thursday, October 22, 2026'])
        ->set('search', 'OPS-301')
        ->assertSee('Thursday, October 22, 2026')
        ->assertDontSee('Friday, October 23, 2026');
});

test('search matches are highlighted in the history', function () {
    $user = User::factory()->create();
    DailyUpdate::factory()->for($user)->create(['date' => '2026-10-22', 'done' => '<ul><li><p>Fixed OPS-301 issue</p></li></ul>']);

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->assertDontSeeHtml('<mark>')
        ->set('search', 'ops-301')
        ->assertSeeHtml('<mark>OPS-301</mark>');
});

test('highlighting only marks text and keeps markup intact', function (string $html, string $term, string $expected) {
    $update = DailyUpdate::factory()->make(['up_next' => $html]);

    expect($update->highlighted('up_next', $term))->toBe($expected);
})->with([
    'every match, any case' => [
        '<p>PDF link and pdf label</p>',
        'pdf',
        '<p><mark>PDF</mark> link and <mark>pdf</mark> label</p>',
    ],
    'link targets are left alone' => [
        '<p><a href="https://example.test/abc-102">ABC-102</a></p>',
        'abc',
        '<p><a href="https://example.test/abc-102"><mark>ABC</mark>-102</a></p>',
    ],
    'regex characters are matched literally' => [
        '<p>like on test order (123)</p>',
        '(123)',
        '<p>like on test order <mark>(123)</mark></p>',
    ],
    'matched markup stays escaped' => [
        '<p>Saw &lt;script&gt; in a log</p>',
        '<script>',
        '<p>Saw <mark>&lt;script&gt;</mark> in a log</p>',
    ],
    'no match leaves the section as stored' => [
        '<ul><li><p>Check-in w/ Sam</p></li></ul>',
        'OPS',
        '<ul><li><p>Check-in w/ Sam</p></li></ul>',
    ],
]);

test('the plain text copy lists each section under its own heading', function () {
    $update = DailyUpdate::factory()->make([
        'done' => '<ul><li><p>Finished up <strong>ABC-102</strong></p><ul><li><p>Queued a test email</p></li></ul></li><li><p>Fixed OPS-301</p></li></ul>',
        'up_next' => null,
    ]);

    expect($update->toPlainText())->toBe(
        "Done:\n- Finished up ABC-102\n  - Queued a test email\n- Fixed OPS-301"
    );
});

test('the huddle copy uses the huddle headings', function () {
    $update = DailyUpdate::factory()->make([
        'done' => '<ul><li><p>Fixed OPS-301</p></li></ul>',
        'up_next' => '<p>Product page redesign</p>',
    ]);

    expect($update->toHuddleText())->toBe("Yesterday:\n- Fixed OPS-301");
});

test('the huddle shares the log of the most recent workday before today', function () {
    $user = User::factory()->create();
    DailyUpdate::factory()->for($user)->create(['date' => '2026-10-22', 'done' => '<p>Thursday work</p>']);
    DailyUpdate::factory()->for($user)->create(['date' => '2026-10-23', 'done' => '<p>Fixed OPS-301</p>', 'up_next' => '<p>Product page redesign</p>']);
    DailyUpdate::factory()->for($user)->create(['date' => '2026-10-26', 'done' => '<p>Monday work</p>']);

    $this->actingAs($user);
    $this->travelTo(today()->setDate(2026, 10, 26));

    Livewire::test(Huddle::class)
        ->assertSee('From your log for Friday, October 23.')
        ->assertSeeInOrder(['Yesterday', 'Fixed OPS-301'])
        ->assertDontSee('Product page redesign')
        ->assertDontSee('Thursday work')
        ->assertDontSee('Monday work');
});

test('the huddle says when there is nothing to share yet', function () {
    DailyUpdate::factory()->create(['date' => '2026-10-23', 'done' => '<p>Someone else\'s work</p>']);

    $this->actingAs(User::factory()->create());
    $this->travelTo(today()->setDate(2026, 10, 26));

    $this->get(route('huddle'))
        ->assertOk()
        ->assertSee('Nothing to share yet')
        ->assertDontSee('Someone else\'s work');
});
