/**
 * A calendar popover bound to a Livewire property holding a Y-m-d date.
 *
 * Dates are handled as local calendar days and formatted by hand, never through
 * toISOString(), which would shift them into UTC.
 */
export default function datePicker({ property }) {
    const toIso = (date) =>
        [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'), String(date.getDate()).padStart(2, '0')].join('-');

    const fromIso = (iso) => {
        const [year, month, day] = (iso || '').split('-').map(Number);

        return year && month && day ? new Date(year, month - 1, day) : new Date();
    };

    return {
        open: false,

        /** The first day of the month on display. */
        month: new Date(),

        get selected() {
            return this.$wire[property];
        },

        get today() {
            return toIso(new Date());
        },

        get label() {
            return fromIso(this.selected).toLocaleDateString(undefined, { month: '2-digit', day: '2-digit', year: 'numeric' });
        },

        get monthLabel() {
            return this.month.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
        },

        get weekdays() {
            // January 1, 2023 was a Sunday.
            return Array.from({ length: 7 }, (_, index) =>
                new Date(2023, 0, 1 + index).toLocaleDateString(undefined, { weekday: 'short' }).slice(0, 2),
            );
        },

        /** Six weeks of days covering the month on display, starting on a Sunday. */
        get days() {
            const start = new Date(this.month.getFullYear(), this.month.getMonth(), 1 - this.month.getDay());

            return Array.from({ length: 42 }, (_, index) => {
                const date = new Date(start.getFullYear(), start.getMonth(), start.getDate() + index);

                return {
                    iso: toIso(date),
                    day: date.getDate(),
                    inMonth: date.getMonth() === this.month.getMonth(),
                    label: date.toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' }),
                };
            });
        },

        toggle() {
            this.open ? this.close() : this.show();
        },

        show() {
            const current = fromIso(this.selected);
            this.month = new Date(current.getFullYear(), current.getMonth(), 1);
            this.open = true;
            this.$nextTick(() => this.$refs.panel.querySelector('[aria-current="date"], [data-selected]')?.focus());
        },

        close(returnFocus = true) {
            this.open = false;

            if (returnFocus) {
                this.$refs.trigger.focus();
            }
        },

        shiftMonth(by) {
            this.month = new Date(this.month.getFullYear(), this.month.getMonth() + by, 1);
        },

        pick(iso) {
            this.close();

            if (iso !== this.selected) {
                this.$wire.$set(property, iso);
            }
        },
    };
}
