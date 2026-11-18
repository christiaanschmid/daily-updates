<?php

use App\Events\CloudSynced;
use App\Livewire\Updates\Show;
use App\Models\DailyUpdate;
use App\Models\User;
use App\Sync\LogMerge;
use Livewire\Livewire;

/**
 * Change a saved log the way another device or browser tab would, behind the open page's back.
 */
function changeElsewhere(DailyUpdate $log, string $done): void
{
    DailyUpdate::query()->whereKey($log->id)->update(['done' => $done]);
}

beforeEach(function () {
    $this->user = User::factory()->cloudPlan()->create();
    $this->actingAs($this->user);
});

// --- Combining two versions ------------------------------------------------

test('bullets added on both sides join one list, without repeating the shared ones', function () {
    expect(LogMerge::html(
        '<ul><li><p>Shared</p></li><li><p>From the web</p></li></ul>',
        '<ul><li><p>Shared</p></li><li><p>From the Mac</p></li></ul>',
    ))->toBe('<ul><li><p>Shared</p></li><li><p>From the web</p></li><li><p>From the Mac</p></li></ul>');
});

test('paragraphs only on the second side go below, and identical versions stay as they are', function () {
    expect(LogMerge::html('<p>Start</p><p>Web</p>', '<p>Start</p><p>Mac</p>'))->toBe('<p>Start</p><p>Web</p><p>Mac</p>')
        ->and(LogMerge::html('<p>Same</p>', '<p>Same</p>'))->toBe('<p>Same</p>')
        ->and(LogMerge::html(null, '<p>New</p>'))->toBe('<p>New</p>')
        ->and(LogMerge::html('<p>Kept</p>', null))->toBe('<p>Kept</p>')
        ->and(LogMerge::html('<p>Café ✓</p>', '<p>Naïve</p>'))->toBe('<p>Café ✓</p><p>Naïve</p>');
});

test('with the version both started from, an edited bullet replaces the old one and deletions stick', function () {
    $list = fn (string ...$items) => '<ul>'.implode('', array_map(fn ($item) => "<li><p>{$item}</p></li>", $items)).'</ul>';

    // Edited here, added to elsewhere.
    expect(LogMerge::threeWay($list('Draft', 'Shared'), $list('Draft, finished', 'Shared'), $list('Draft', 'Shared', 'From the web')))
        ->toBe($list('Draft, finished', 'Shared', 'From the web'))
        // Deleted here, added to elsewhere.
        ->and(LogMerge::threeWay($list('A', 'B'), $list('A'), $list('A', 'B', 'C')))->toBe($list('A', 'C'))
        // Added on both sides.
        ->and(LogMerge::threeWay($list('A'), $list('A', 'Mine'), $list('A', 'Theirs')))->toBe($list('A', 'Theirs', 'Mine'))
        // The same bullet edited differently on each side: both are kept.
        ->and(LogMerge::threeWay($list('X'), $list('X here'), $list('X there')))->toBe($list('X there', 'X here'));
});

// --- Saving over a change made elsewhere -----------------------------------

test('saving after the log changed elsewhere keeps both, and sends the result to the editor', function () {
    $log = DailyUpdate::factory()->for($this->user)->create(['date' => '2026-10-26', 'done' => '<ul><li><p>Shared</p></li></ul>']);

    $page = Livewire::test(Show::class, ['date' => '2026-10-26'])->call('edit');

    changeElsewhere($log, '<ul><li><p>Shared</p></li><li><p>Added on the web</p></li></ul>');

    $page->set('done', '<ul><li><p>Shared</p></li><li><p>Typed here</p></li></ul>')
        ->assertDispatched('log-refreshed', fn (string $name, array $params) => str_contains($params['sections']['done'], 'Added on the web')
            && str_contains($params['sections']['done'], 'Typed here'));

    expect($log->fresh()->done)->toBe('<ul><li><p>Shared</p></li><li><p>Added on the web</p></li><li><p>Typed here</p></li></ul>');
});

test('a bullet still being typed while the log changes elsewhere isn\'t repeated', function () {
    $log = DailyUpdate::factory()->for($this->user)->create(['date' => '2026-10-26', 'done' => '<ul><li><p>Typed</p></li></ul>']);
    $page = Livewire::test(Show::class, ['date' => '2026-10-26'])->call('edit');

    changeElsewhere($log, '<ul><li><p>Typed</p></li><li><p>From the web</p></li></ul>');

    // Saved partway through typing, then again when done, before the editor shows the web's bullet.
    $page->set('done', '<ul><li><p>Typed and more</p></li></ul>')
        ->set('done', '<ul><li><p>Typed and more, finished</p></li></ul>');

    expect($log->fresh()->done)->toBe('<ul><li><p>Typed and more, finished</p></li><li><p>From the web</p></li></ul>');
});

test('once the editor shows the combined log, deleting a bullet sticks', function () {
    $log = DailyUpdate::factory()->for($this->user)->create(['date' => '2026-10-26', 'done' => '<ul><li><p>Shared</p></li></ul>']);
    $page = Livewire::test(Show::class, ['date' => '2026-10-26'])->call('edit');
    changeElsewhere($log, '<ul><li><p>Shared</p></li><li><p>Web</p></li></ul>');
    $page->set('done', '<ul><li><p>Shared</p></li><li><p>Mac</p></li></ul>');

    $combined = $log->fresh();
    $page->call('acknowledgeRefresh', md5(json_encode(['done' => $combined->done, 'up_next' => $combined->up_next])))
        ->assertSet('done', $combined->done);

    $page->set('done', '<ul><li><p>Shared</p></li><li><p>Mac</p></li></ul>')->assertNotDispatched('log-refreshed');

    expect($log->fresh()->done)->toBe('<ul><li><p>Shared</p></li><li><p>Mac</p></li></ul>');
});

test('without a change elsewhere, saving writes the editor\'s version, deletions included', function () {
    $log = DailyUpdate::factory()->for($this->user)->create(['date' => '2026-10-26', 'done' => '<ul><li><p>One</p></li><li><p>Two</p></li></ul>']);

    Livewire::test(Show::class, ['date' => '2026-10-26'])
        ->call('edit')
        ->set('done', '<ul><li><p>One</p></li></ul>')
        ->set('done', '<ul><li><p>One</p></li><li><p>Three</p></li></ul>')
        ->assertNotDispatched('log-refreshed');

    expect($log->fresh()->done)->toBe('<ul><li><p>One</p></li><li><p>Three</p></li></ul>');
});

test('a day started here and also started elsewhere keeps both', function () {
    $page = Livewire::test(Show::class, ['date' => '2026-10-26'])->assertSet('editing', true);

    DailyUpdate::factory()->for($this->user)->create(['date' => '2026-10-26', 'done' => '<p>Started on the web</p>']);

    $page->set('done', '<p>Started here</p>');

    expect($this->user->dailyUpdates()->sole()->done)->toBe('<p>Started on the web</p><p>Started here</p>');
});

test('starting to edit uses the log as saved now, not as it was when the page opened', function () {
    $log = DailyUpdate::factory()->for($this->user)->create(['date' => '2026-10-26', 'done' => '<p>Old</p>']);
    $page = Livewire::test(Show::class, ['date' => '2026-10-26']);

    changeElsewhere($log, '<p>Newer</p>');

    $page->call('edit')->assertSet('done', '<p>Newer</p>');
});

// --- Noticing changes while the page is open -------------------------------

test('a page that isn\'t editing shows a change made elsewhere', function () {
    $log = DailyUpdate::factory()->for($this->user)->create(['date' => '2026-10-26', 'done' => '<p>Old</p>']);
    $page = Livewire::test(Show::class, ['date' => '2026-10-26']);

    changeElsewhere($log, '<p>From the Mac</p>');

    $page->call('checkForChanges')->assertSet('done', '<p>From the Mac</p>')->assertSee('From the Mac');
});

test('an open editor is sent a change made elsewhere', function () {
    $log = DailyUpdate::factory()->for($this->user)->create(['date' => '2026-10-26', 'done' => '<p>Old</p>']);
    $page = Livewire::test(Show::class, ['date' => '2026-10-26'])->call('edit');

    $page->call('checkForChanges')->assertNotDispatched('log-refreshed');

    changeElsewhere($log, '<p>From the Mac</p>');

    $page->call('checkForChanges')
        ->assertDispatched('log-refreshed', fn (string $name, array $params) => $params['sections']['done'] === '<p>From the Mac</p>');
});

test('the web page checks for changes every so often, and the Mac app when it syncs', function () {
    DailyUpdate::factory()->for($this->user)->create(['date' => '2026-10-26']);

    $this->get(route('updates.show', '2026-10-26'))->assertSee('wire:poll.20s="checkForChanges"', false);

    config(['nativephp-internal.running' => true]);
    $this->get(route('updates.show', '2026-10-26'))->assertDontSee('wire:poll.20s', false);

    Livewire::test(Show::class, ['date' => '2026-10-26'])
        ->dispatch('native:'.CloudSynced::class)
        ->assertHasNoErrors();
});
