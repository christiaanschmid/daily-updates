import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';

/**
 * A Tiptap editor bound to a Livewire property.
 *
 * Every change is queued on the component right away, so a submit always sends the
 * latest HTML, and is committed after a pause in typing so the section autosaves.
 * The Tiptap instance is kept out of Alpine's reactive state, which it does not
 * survive being proxied by.
 *
 * When the log changes elsewhere, such as on another device, the component sends
 * the combined log in a "log-refreshed" event. The editor shows it once typing has
 * paused and been saved, so no keystroke is lost and the cursor stays put.
 */
export default function richEditor({ property, label, autofocus = false, debounce = 2000 }) {
    let editor = null;
    let commitTimer = null;
    let committing = false;
    let lastTypedAt = 0;
    let refreshTimer = null;
    let pendingRefresh = null;

    /** How long after the last keystroke a change from elsewhere may replace the text. */
    const typingPauseMs = 1500;

    return {
        /** Which marks and nodes are active at the cursor, for the toolbar. */
        active: {},

        init() {
            editor = new Editor({
                element: this.$refs.content,
                content: this.$wire.$get(property) || '',
                extensions: [
                    StarterKit.configure({
                        heading: false,
                        codeBlock: false,
                        horizontalRule: false,
                        trailingNode: false,
                        link: { openOnClick: false, autolink: true, defaultProtocol: 'https' },
                    }),
                ],
                editorProps: {
                    attributes: {
                        class: 'du-prose du-editor-content',
                        role: 'textbox',
                        'aria-multiline': 'true',
                        'aria-label': label,
                    },
                },
                onUpdate: () => this.queueSave(),
                onTransaction: () => this.refreshActive(),
            });

            this.refreshActive();

            this.$wire.$on('log-refreshed', (event) => {
                const { sections, hash } = Array.isArray(event) ? event[0] : event;

                if (!(property in sections)) {
                    return;
                }

                pendingRefresh = { html: sections[property] || '', hash };
                this.applyRefresh();
            });

            if (autofocus) {
                editor.commands.focus('end');
            }
        },

        queueSave() {
            lastTypedAt = Date.now();
            this.$wire.$set(property, editor.getHTML(), false);

            clearTimeout(commitTimer);
            commitTimer = setTimeout(() => {
                commitTimer = null;
                committing = true;
                // Again, in case a response in the meantime replaced it with the saved text.
                this.$wire.$set(property, editor.getHTML(), false);
                Promise.resolve(this.$wire.$commit()).finally(() => {
                    committing = false;
                });
            }, debounce);
        },

        /**
         * Show the log as saved elsewhere, once nothing typed here is still unsaved.
         * Otherwise wait: saving that typing combines it with the change and sends
         * the result here again.
         */
        applyRefresh() {
            clearTimeout(refreshTimer);

            if (!pendingRefresh) {
                return;
            }

            if (commitTimer || committing || Date.now() - lastTypedAt < typingPauseMs) {
                refreshTimer = setTimeout(() => this.applyRefresh(), 500);

                return;
            }

            const { html, hash } = pendingRefresh;
            pendingRefresh = null;

            if (html !== editor.getHTML()) {
                const { from, to } = editor.state.selection;
                const hadFocus = editor.isFocused;

                editor.commands.setContent(html, { emitUpdate: false });

                const end = editor.state.doc.content.size;
                editor.commands.setTextSelection({ from: Math.min(from, end), to: Math.min(to, end) });

                if (hadFocus) {
                    editor.commands.focus();
                }
            }

            // Not $set: that would save again. The component takes the saved text itself.
            this.$wire.acknowledgeRefresh(hash);
        },

        refreshActive() {
            this.active = Object.fromEntries(
                ['bold', 'italic', 'underline', 'strike', 'code', 'link', 'bulletList', 'orderedList'].map((name) => [name, editor.isActive(name)]),
            );
        },

        /**
         * Run a Tiptap chain command, such as "toggleBold", keeping focus in the editor.
         */
        run(command, ...args) {
            editor.chain().focus()[command](...args).run();
        },

        toggleLink() {
            if (editor.isActive('link')) {
                this.run('unsetLink');

                return;
            }

            const url = window.prompt('Link address', 'https://');

            if (url && url.trim() !== '' && url.trim() !== 'https://') {
                editor.chain().focus().extendMarkRange('link').setLink({ href: url.trim() }).run();
            }
        },

        undo() {
            this.run('undo');
        },

        redo() {
            this.run('redo');
        },

        destroy() {
            clearTimeout(commitTimer);
            clearTimeout(refreshTimer);
            editor?.destroy();
        },
    };
}
