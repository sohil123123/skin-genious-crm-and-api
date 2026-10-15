{{--
    Calendar dropdown rendered beside the Filters button on the appointments
    list (TablesRenderHook::TOOLBAR_SEARCH_AFTER, scoped to ListAppointments).

    Picking a day sets the table's `calendar_date` filter through $wire, so it
    behaves like any other filter: it shows an indicator chip, can be removed
    there, and is mirrored in the Filters modal. Picking the selected day again
    clears it. wire:ignore keeps Livewire's morph from resetting the dropdown;
    the selected date is read reactively from $wire, so it stays in sync when
    the filter is changed elsewhere.
--}}
<div
    wire:ignore
    class="apt-cal"
    x-data="{
        open: false,
        view: null,
        weekdays: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],

        get selected() {
            const value = $wire.tableFilters?.calendar_date?.date ?? null;
            return value ? String(value).substring(0, 10) : null;
        },

        init() {
            this.jumpTo(this.selected);
        },

        iso(date) {
            const pad = (n) => String(n).padStart(2, '0');
            return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
        },

        jumpTo(isoDate) {
            const date = isoDate ? new Date(isoDate + 'T00:00:00') : new Date();
            this.view = new Date(date.getFullYear(), date.getMonth(), 1);
        },

        shift(months) {
            this.view = new Date(this.view.getFullYear(), this.view.getMonth() + months, 1);
        },

        monthLabel(date) {
            return date.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' });
        },

        get selectedLabel() {
            return this.selected
                ? new Date(this.selected + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
                : null;
        },

        {{-- Six Monday-first weeks, including the trailing/leading days of the neighbouring months. --}}
        get cells() {
            const first = this.view;
            const offset = (first.getDay() + 6) % 7;
            const start = new Date(first.getFullYear(), first.getMonth(), 1 - offset);
            const today = this.iso(new Date());

            return Array.from({ length: 42 }, (_, i) => {
                const date = new Date(start.getFullYear(), start.getMonth(), start.getDate() + i);
                const iso = this.iso(date);

                return {
                    iso,
                    day: date.getDate(),
                    outside: date.getMonth() !== first.getMonth(),
                    today: iso === today,
                    selected: iso === this.selected,
                };
            });
        },

        toggle() {
            if (! this.open) {
                this.jumpTo(this.selected);
            }
            this.open = ! this.open;
        },

        pick(iso) {
            $wire.$set('tableFilters.calendar_date.date', iso === this.selected ? null : iso);
            this.open = false;
        },

        clear() {
            $wire.$set('tableFilters.calendar_date.date', null);
            this.open = false;
        },
    }"
    x-on:keydown.escape.window="open = false"
    x-on:click.outside="open = false"
>
    <button
        type="button"
        class="apt-cal-trigger"
        x-bind:class="{ 'is-active': selected }"
        x-on:click="toggle()"
        x-bind:title="selected ? 'Showing ' + selectedLabel : 'Filter by date'"
    >
        <x-filament::icon icon="heroicon-o-calendar-days" class="apt-cal-trigger-icon" />
        <span x-show="selected" x-text="selectedLabel" x-cloak></span>
    </button>

    <div x-show="open" x-transition.origin.top.right x-cloak class="apt-cal-panel">
        <div class="apt-cal-head">
            <span class="apt-cal-title" x-text="selected ? selectedLabel : 'All dates'"></span>

            <div class="apt-cal-nav">
                <button type="button" class="apt-cal-round" x-on:click="shift(-1)" aria-label="Previous month">
                    <x-filament::icon icon="heroicon-m-chevron-left" class="apt-cal-chevron" />
                </button>
                <button type="button" class="apt-cal-pill" x-on:click="jumpTo(null)" title="Go to current month" x-text="monthLabel(view)"></button>
                <button type="button" class="apt-cal-round" x-on:click="shift(1)" aria-label="Next month">
                    <x-filament::icon icon="heroicon-m-chevron-right" class="apt-cal-chevron" />
                </button>
            </div>
        </div>

        <div class="apt-cal-grid">
            <template x-for="name in weekdays" :key="name">
                <div class="apt-cal-weekday" x-text="name"></div>
            </template>

            <template x-for="cell in cells" :key="cell.iso">
                <button
                    type="button"
                    class="apt-cal-day"
                    x-bind:class="{ 'is-outside': cell.outside, 'is-today': cell.today, 'is-selected': cell.selected }"
                    x-on:click="pick(cell.iso)"
                    x-text="cell.day"
                ></button>
            </template>
        </div>

        <div class="apt-cal-foot">
            <button type="button" class="apt-cal-link" x-on:click="pick(iso(new Date()))">Today</button>
            <button type="button" class="apt-cal-link is-muted" x-show="selected" x-on:click="clear()">Clear date</button>
        </div>
    </div>

    {{-- Colours come from the panel theme (--primary-* / --gray-*), so the
         calendar follows AdminPanelProvider's ->colors() and dark mode. --}}
    <style>
        .apt-cal { position: relative; }

        .apt-cal-trigger {
            display: inline-flex; align-items: center; gap: .4rem;
            height: 2.25rem; min-width: 2.25rem; padding: 0 .6rem; justify-content: center;
            border-radius: .5rem; font-size: .8125rem; font-weight: 600;
            border: 1px solid var(--gray-300); background: #fff; color: var(--gray-700); cursor: pointer;
        }
        .apt-cal-trigger:hover { background: var(--gray-50); }
        .apt-cal-trigger.is-active { border-color: var(--primary-500); color: var(--primary-600); background: var(--primary-50); }
        .apt-cal-trigger-icon { width: 1.25rem; height: 1.25rem; }

        .apt-cal-panel {
            position: absolute; right: 0; top: calc(100% + .5rem); z-index: 40;
            width: min(34rem, calc(100vw - 2rem));
            padding: 1.5rem 1.25rem 1rem;
            background: #fff; border-radius: .75rem;
            border: 1px solid var(--gray-200);
            box-shadow: 0 10px 30px rgba(0, 0, 0, .10), 0 1px 3px rgba(0, 0, 0, .06);
        }
        .apt-cal-head { display: flex; align-items: center; justify-content: space-between; gap: .75rem; margin-bottom: 1rem; flex-wrap: wrap; }
        .apt-cal-title { font-size: .875rem; font-weight: 500; color: var(--gray-700); }
        .apt-cal-nav { display: flex; align-items: center; gap: .35rem; }
        .apt-cal-round, .apt-cal-pill {
            border: 0; cursor: pointer; color: #fff; background: var(--primary-600);
            height: 2rem; display: inline-flex; align-items: center; justify-content: center;
        }
        .apt-cal-round { width: 2rem; border-radius: 9999px; }
        .apt-cal-pill { padding: 0 1.1rem; border-radius: 9999px; font-size: .8125rem; font-weight: 600; min-width: 8.5rem; }
        .apt-cal-round:hover, .apt-cal-pill:hover { background: var(--primary-500); }
        .apt-cal-chevron { width: 1rem; height: 1rem; }

        .apt-cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); }
        .apt-cal-weekday {
            text-align: center; padding: .75rem 0 1rem; font-size: .8125rem; font-weight: 600; color: var(--primary-600);
            border-left: 1px solid var(--gray-100);
        }
        .apt-cal-weekday:nth-child(7n + 1) { border-left: 0; }
        .apt-cal-day {
            text-align: left; padding: .55rem .7rem; font-size: .8125rem; color: var(--gray-800);
            background: transparent; border: 0; cursor: pointer;
            border-top: 1px solid var(--gray-100); border-left: 1px solid var(--gray-100);
        }
        .apt-cal-grid > .apt-cal-day:nth-child(7n + 1) { border-left: 0; }
        .apt-cal-day:hover { background: var(--primary-50); color: var(--primary-700); }
        .apt-cal-day.is-outside { color: var(--gray-300); }
        .apt-cal-day.is-today { background: var(--gray-100); font-weight: 600; box-shadow: inset 0 0 0 1px var(--primary-300); }
        .apt-cal-day.is-selected { background: var(--primary-600); color: #fff; font-weight: 600; box-shadow: none; }

        .apt-cal-foot { display: flex; justify-content: space-between; margin-top: .75rem; }
        .apt-cal-link { border: 0; background: none; cursor: pointer; font-size: .8125rem; font-weight: 600; color: var(--primary-600); padding: .25rem 0; }
        .apt-cal-link:hover { color: var(--primary-500); }
        .apt-cal-link.is-muted { color: var(--gray-500); }

        .dark .apt-cal-trigger { background: transparent; border-color: var(--gray-700); color: var(--gray-200); }
        .dark .apt-cal-trigger:hover { background: var(--gray-800); }
        .dark .apt-cal-trigger.is-active { border-color: var(--primary-500); color: var(--primary-400); background: color-mix(in srgb, var(--primary-500) 15%, transparent); }
        .dark .apt-cal-panel { background: var(--gray-900); border-color: var(--gray-700); box-shadow: 0 10px 30px rgba(0, 0, 0, .5); }
        .dark .apt-cal-title, .dark .apt-cal-day { color: var(--gray-200); }
        .dark .apt-cal-weekday { color: var(--primary-400); }
        .dark .apt-cal-day, .dark .apt-cal-weekday { border-color: var(--gray-800); }
        .dark .apt-cal-day:hover { background: color-mix(in srgb, var(--primary-500) 15%, transparent); color: var(--primary-300); }
        .dark .apt-cal-day.is-outside { color: var(--gray-600); }
        .dark .apt-cal-day.is-today { background: var(--gray-800); box-shadow: inset 0 0 0 1px var(--primary-500); }
        .dark .apt-cal-day.is-selected { background: var(--primary-500); color: #fff; }
        .dark .apt-cal-round, .dark .apt-cal-pill { background: var(--primary-500); }
        .dark .apt-cal-round:hover, .dark .apt-cal-pill:hover { background: var(--primary-400); }
        .dark .apt-cal-link { color: var(--primary-400); }
        .dark .apt-cal-link.is-muted { color: var(--gray-400); }
    </style>
</div>
