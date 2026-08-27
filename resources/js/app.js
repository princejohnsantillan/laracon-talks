import Alpine from 'alpinejs';

window.Alpine = Alpine;

/**
 * The archive filter.
 *
 * Every one of the 626 rows is rendered by Blade, so the first paint already
 * has the whole catalog. Alpine only hides and shows what is already there,
 * and keeps the hero ledger in step: one tick per talk, lit when it matches.
 */
Alpine.data('archive', (total) => ({
    query: '',
    conference: 'all',
    year: null,
    shown: total,
    total,
    rows: [],
    tickRows: [],

    init() {
        // The row's own text is the search index — year, conference, title,
        // speaker and summary are all already on screen.
        this.rows = Array.from(this.$root.querySelectorAll('tbody tr')).map((el) => ({
            el,
            haystack: el.textContent.toLowerCase().replace(/\s+/g, ' '),
            conference: el.dataset.conference,
            year: el.dataset.year,
        }));

        this.tickRows = Array.from(this.$root.querySelectorAll('[data-tick-year]')).map((el) => ({
            year: el.dataset.tickYear,
            ticks: Array.from(el.querySelectorAll('i')),
        }));

        // The table header sticks directly beneath the filter bar, so it needs
        // the bar's real height rather than a guessed one.
        const controls = document.querySelector('.controls');
        const measure = () =>
            document.documentElement.style.setProperty('--controls-h', `${controls.offsetHeight}px`);

        measure();
        new ResizeObserver(measure).observe(controls);

        this.$watch('query', () => this.apply());
        this.$watch('conference', () => this.apply());
        this.$watch('year', () => this.apply());
    },

    get filtered() {
        return this.query !== '' || this.conference !== 'all' || this.year !== null;
    },

    setConference(slug) {
        this.conference = this.conference === slug ? 'all' : slug;
    },

    toggleYear(year) {
        this.year = this.year === year ? null : year;
    },

    clear() {
        this.query = '';
        this.conference = 'all';
        this.year = null;
        this.$refs.search?.focus();
    },

    /** Focus search on "/" the way a code search would. */
    focusSearch(event) {
        const tag = document.activeElement?.tagName;

        if (tag === 'INPUT' || tag === 'TEXTAREA') {
            return;
        }

        event.preventDefault();
        this.$refs.search.focus();
    },

    apply() {
        const terms = this.query.toLowerCase().split(/\s+/).filter(Boolean);
        const perYear = new Map();
        let shown = 0;

        for (const row of this.rows) {
            const match =
                (this.conference === 'all' || row.conference === this.conference) &&
                (this.year === null || row.year === this.year) &&
                terms.every((term) => row.haystack.includes(term));

            row.el.classList.toggle('row-hidden', !match);

            if (match) {
                shown++;
                perYear.set(row.year, (perYear.get(row.year) ?? 0) + 1);
            }
        }

        this.shown = shown;

        for (const { year, ticks } of this.tickRows) {
            const lit = perYear.get(year) ?? 0;

            ticks.forEach((tick, index) => tick.classList.toggle('lit', index < lit));
        }
    },
}));

/**
 * Theme. The inline script in <head> sets the class before paint; this only
 * handles the toggle and remembers the choice.
 */
Alpine.data('theme', () => ({
    dark: document.documentElement.classList.contains('dark'),

    toggle() {
        this.dark = !this.dark;
        document.documentElement.classList.toggle('dark', this.dark);
        localStorage.setItem('theme', this.dark ? 'dark' : 'light');
    },
}));

Alpine.start();
