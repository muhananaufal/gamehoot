// U15: the date range picker of the Events list. Dates are 'YYYY-MM-DD' strings in WIB (T10) and
// are counted with UTC arithmetic, so the viewer's own time zone never shifts a day. Keyboard use
// follows the W3C APG date picker grid: arrows move a day or a week, Page Up/Down a month,
// Home/End the start or end of the week.

const DAY_MS = 86_400_000;

function toTime(date) {
    const [year, month, day] = date.split('-').map(Number);

    return Date.UTC(year, month - 1, day);
}

function fromTime(time) {
    return new Date(time).toISOString().slice(0, 10);
}

export function addDays(date, days) {
    return fromTime(toTime(date) + days * DAY_MS);
}

/** The same day in another month, or that month's last day when it is shorter. */
export function addMonths(date, months) {
    const [year, month, day] = date.split('-').map(Number);
    const target = new Date(Date.UTC(year, month - 1 + months, 1));
    const last = new Date(Date.UTC(target.getUTCFullYear(), target.getUTCMonth() + 1, 0)).getUTCDate();
    target.setUTCDate(Math.min(day, last));

    return fromTime(target.getTime());
}

/** Monday 0 ... Sunday 6. */
function weekday(date) {
    return (new Date(toTime(date)).getUTCDay() + 6) % 7;
}

/** The weeks shown for a month (1-12): Monday to Sunday, padded with the neighbouring days. */
export function monthGrid(year, month) {
    const first = `${year}-${String(month).padStart(2, '0')}-01`;
    const last = addDays(addMonths(first, 1), -1);
    let day = addDays(first, -weekday(first));
    const end = addDays(last, 6 - weekday(last));
    const weeks = [];

    while (day <= end) {
        const week = [];

        for (let i = 0; i < 7; i++) {
            week.push({ date: day, day: Number(day.slice(8)), inMonth: day.slice(0, 7) === first.slice(0, 7) });
            day = addDays(day, 1);
        }

        weeks.push(week);
    }

    return weeks;
}

/** First click starts a range, the second closes it (in either order), a third starts again. */
export function pickDay({ from, to }, date) {
    if (from === '' || to !== '') {
        return { from: date, to: '' };
    }

    return date < from ? { from: date, to: from } : { from, to: date };
}

/** How a day cell looks: an end of the range, inside it, or today. */
export function dayCell({ from, to }, date, today) {
    const end = to || from;

    return {
        edge: date === from || date === to,
        inRange: from !== '' && date >= from && date <= end,
        today: date === today,
    };
}

export function presetRange(name, today) {
    switch (name) {
        case 'today':
            return { from: today, to: today };
        case 'last7':
            return { from: addDays(today, -6), to: today };
        case 'last30':
            return { from: addDays(today, -29), to: today };
        case 'month':
            return { from: `${today.slice(0, 7)}-01`, to: addDays(addMonths(`${today.slice(0, 7)}-01`, 1), -1) };
        default:
            return { from: '', to: '' };
    }
}

/** The day the focus moves to for a key, or null when the key is not a grid key. */
export function moveFocus(date, key) {
    switch (key) {
        case 'ArrowLeft':
            return addDays(date, -1);
        case 'ArrowRight':
            return addDays(date, 1);
        case 'ArrowUp':
            return addDays(date, -7);
        case 'ArrowDown':
            return addDays(date, 7);
        case 'PageUp':
            return addMonths(date, -1);
        case 'PageDown':
            return addMonths(date, 1);
        case 'Home':
            return addDays(date, -weekday(date));
        case 'End':
            return addDays(date, 6 - weekday(date));
        default:
            return null;
    }
}

const monthName = new Intl.DateTimeFormat('en-GB', { month: 'long', year: 'numeric', timeZone: 'UTC' });
const shortDay = new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'short', timeZone: 'UTC' });
const longDay = new Intl.DateTimeFormat('en-GB', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    timeZone: 'UTC',
});

export function rangeLabel(from, to) {
    const show = (date) => shortDay.format(new Date(toTime(date)));

    if (from === '' && to === '') {
        return '';
    }

    return to === '' || from === to ? show(from || to) : `${show(from)} – ${show(to)}`;
}

/**
 * The Alpine component of the picker. It lives inside a tableView and edits its `from` and
 * `to` on Apply; until then it works on a draft.
 */
export function dateRangePicker({ today }) {
    return {
        today,
        draftFrom: '',
        draftTo: '',
        focusDay: today,
        // The first of the left-hand month; the right-hand one follows it.
        shownMonth: `${today.slice(0, 7)}-01`,
        // The two months on screen, rebuilt only when shownMonth changes: rebuilding the grid on
        // every change made each day cell re-run its bindings (seconds per click).
        grid: [],

        init() {
            this.grid = this.buildGrid();
            this.$watch('shownMonth', () => {
                this.grid = this.buildGrid();
            });
        },

        get label() {
            return rangeLabel(this.from, this.to);
        },

        buildGrid() {
            return [0, 1].map((offset) => {
                const first = addMonths(this.shownMonth, offset);
                const [year, month] = first.split('-').map(Number);

                return { key: first, name: monthName.format(new Date(toTime(first))), weeks: monthGrid(year, month) };
            });
        },

        openPicker() {
            this.draftFrom = this.from;
            this.draftTo = this.to;
            this.focusDay = this.from || this.today;
            this.shownMonth = `${addMonths(this.focusDay, this.focusDay.slice(0, 7) === this.today.slice(0, 7) ? -1 : 0).slice(0, 7)}-01`;
            // Build now, not in the shownMonth watcher: the focus below needs the new days in place.
            this.grid = this.buildGrid();
            this.$refs.picker.showModal();
            this.$nextTick(() => this.focusCell());
        },

        pick(date) {
            ({ from: this.draftFrom, to: this.draftTo } = pickDay({ from: this.draftFrom, to: this.draftTo }, date));
            this.focusDay = date;
        },

        preset(name) {
            ({ from: this.draftFrom, to: this.draftTo } = presetRange(name, this.today));
        },

        /** One call per cell: the classes for its look. */
        cellClass(date) {
            const {
                edge,
                inRange,
                today: isToday,
            } = dayCell({ from: this.draftFrom, to: this.draftTo }, date, this.today);

            if (edge) {
                return 'rounded-control bg-accent font-bold text-on-accent';
            }

            return [
                inRange ? 'bg-subtle text-ink' : 'rounded-control hover:bg-subtle',
                isToday ? 'font-bold underline underline-offset-4' : '',
            ].join(' ');
        },

        dayLabel(date) {
            return longDay.format(new Date(toTime(date)));
        },

        onKey(event) {
            const next = moveFocus(this.focusDay, event.key);

            if (next === null) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    this.pick(this.focusDay);
                }

                return;
            }

            event.preventDefault();
            this.focusDay = next;
            const shownEnd = addDays(addMonths(this.shownMonth, 2), -1);

            if (next < this.shownMonth) {
                this.shownMonth = `${next.slice(0, 7)}-01`;
            } else if (next > shownEnd) {
                this.shownMonth = `${addMonths(next, -1).slice(0, 7)}-01`;
            }

            this.$nextTick(() => this.focusCell());
        },

        focusCell() {
            this.$refs.picker.querySelector(`[data-day="${this.focusDay}"]`)?.focus();
        },

        shift(months) {
            this.shownMonth = addMonths(this.shownMonth, months);
        },

        apply() {
            this.from = this.draftFrom;
            this.to = this.draftTo;
            this.$refs.picker.close();
        },

        clear() {
            this.draftFrom = '';
            this.draftTo = '';
        },
    };
}
