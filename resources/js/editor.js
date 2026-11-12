import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';

/**
 * A Tiptap editor bound to a Livewire property.
 *
 * Every change is queued on the component right away, so a submit always sends the
 * latest HTML, and is committed after a pause in typing so the section autosaves.
 * The Tiptap instance is kept out of Alpine's reactive state, which it does not
 * survive being proxied by.
 */
export default function richEditor({ property, label, autofocus = false, debounce = 2000 }) {
    let editor = null;
    let commitTimer = null;

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

            if (autofocus) {
                editor.commands.focus('end');
            }
        },

        queueSave() {
            this.$wire.$set(property, editor.getHTML(), false);

            clearTimeout(commitTimer);
            commitTimer = setTimeout(() => this.$wire.$commit(), debounce);
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
            editor?.destroy();
        },
    };
}
