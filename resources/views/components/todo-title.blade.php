@props([
    'todo',
    'editable' => true,
])

{{--
    A todo's title, shown with its Markdown. When editable, clicking it (anywhere but a link)
    swaps in a field holding the raw text: Return or clicking away saves through the Livewire
    component's rename(), and Escape cancels.
--}}
<div
    {{ $attributes->class('min-w-0 grow') }}
    @if ($editable)
        x-data="{
            editing: false,
            original: @js($todo->title),
            draft: @js($todo->title),
            start() {
                this.draft = this.original;
                this.editing = true;
                this.$nextTick(() => this.$refs.input.focus());
            },
            save() {
                if (! this.editing) return;
                this.editing = false;
                const title = this.draft.trim();
                if (title === '' || title === this.original) return;
                this.$wire.rename({{ $todo->id }}, title);
            },
            cancel() {
                this.editing = false;
            },
        }"
    @endif
>
    @if ($editable)
        <span
            x-show="! editing"
            x-on:click="$event.target.closest('a') || start()"
            x-on:keydown.enter.prevent="start()"
            role="button"
            tabindex="0"
            aria-label="{{ __('Edit :title', ['title' => $todo->title]) }}"
            class="du-prose block cursor-text text-base leading-relaxed break-words text-zinc-100"
        >{{ $todo->titleHtml() }}</span>

        <input
            type="text"
            x-show="editing"
            x-cloak
            x-ref="input"
            x-model="draft"
            x-on:keydown.enter.prevent="save()"
            x-on:keydown.escape.prevent="cancel()"
            x-on:blur="save()"
            maxlength="{{ \App\Models\Todo::MAX_TITLE_LENGTH }}"
            aria-label="{{ __('Todo title') }}"
            class="block w-full border-0 bg-transparent p-0 text-base leading-relaxed text-zinc-100 focus:ring-0 focus:outline-none"
        />
    @else
        <span class="du-prose block text-base leading-relaxed break-words text-zinc-100">{{ $todo->titleHtml() }}</span>
    @endif
</div>
