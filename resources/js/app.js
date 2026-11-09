import Alpine from 'alpinejs';

window.Alpine = Alpine;

const searchable = (value) => value.toLowerCase().normalize('NFKD')
    .replace(/\p{Diacritic}/gu, '').replace(/ł/g, 'l').replace(/ø/g, 'o').replace(/ß/g, 'ss');

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
    ledgerOpen: false,
    conferenceCounts: {},

    init() {
        // The row's own text is the search index — year, conference, title,
        // speaker and summary are all already on screen.
        this.rows = Array.from(this.$root.querySelectorAll('tbody tr')).map((el) => ({
            el,
            haystack: searchable(el.textContent).replace(/\s+/g, ' '),
            conference: el.dataset.conference,
            year: el.dataset.year,
        }));

        this.tickRows = Array.from(this.$root.querySelectorAll('[data-tick-year]')).map((el) => ({
            el,
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

        this.readUrl();
        this.apply(false);
        window.addEventListener('popstate', () => {
            this.readUrl();
            this.apply(false);
        });
        this.$watch('query', () => this.apply());
        this.$watch('conference', () => this.apply());
        this.$watch('year', () => this.apply());
    },

    readUrl() {
        const params = new URLSearchParams(location.search);
        this.query = params.get('q') ?? '';
        this.conference = this.rows.some(row => row.conference === params.get('conference'))
            ? params.get('conference') : 'all';
        this.year = this.rows.some(row => row.year === params.get('year')) ? params.get('year') : null;
    },

    get filtered() {
        return this.query !== '' || this.conference !== 'all' || this.year !== null;
    },

    setConference(slug) {
        this.conference = this.conference === slug ? 'all' : slug;
    },

    toggleYear(year) {
        this.year = this.year === year ? null : year;
        if (matchMedia('(max-width: 1023px)').matches) {
            this.ledgerOpen = false;
            this.$nextTick(() => {
                const controls = document.querySelector('.controls');
                controls.focus({ preventScroll: true });
                controls.scrollIntoView({ block: 'start', behavior: 'instant' });
            });
        }
    },

    clear() {
        this.query = '';
        this.conference = 'all';
        this.year = null;
        this.$refs.search?.focus();
    },

    /** Focus search on "/" the way a code search would. */
    focusSearch(event) {
        if (document.activeElement?.matches('input, textarea, select, [contenteditable]') ||
            event.ctrlKey || event.metaKey || event.altKey) {
            return;
        }

        event.preventDefault();
        this.$refs.search.focus();
    },

    apply(updateUrl = true) {
        const terms = searchable(this.query).split(/\s+/).filter(Boolean);
        const perYear = new Map();
        const perConference = { all: 0 };
        let shown = 0;

        for (const row of this.rows) {
            const matchesQuery = terms.every((term) => row.haystack.includes(term));
            const matchesConference = this.conference === 'all' || row.conference === this.conference;
            const matchesYear = this.year === null || row.year === this.year;
            const match = matchesQuery && matchesConference && matchesYear;
            row.el.classList.toggle('row-hidden', !match);
            if (match) shown++;

            // Facet counts show what choosing that year/conference would return.
            if (matchesQuery && matchesConference) {
                perYear.set(row.year, (perYear.get(row.year) ?? 0) + 1);
            }
            if (matchesQuery && matchesYear) {
                perConference.all++;
                perConference[row.conference] = (perConference[row.conference] ?? 0) + 1;
            }
        }

        this.shown = shown;
        this.conferenceCounts = perConference;

        for (const { el, year, ticks } of this.tickRows) {
            const lit = perYear.get(year) ?? 0;
            el.querySelector('.ledger-count').textContent = lit;
            el.setAttribute('aria-label', `Filter by ${year} — ${lit} matching talks`);
            ticks.forEach((tick, index) => tick.classList.toggle('lit', index < lit));
        }

        if (updateUrl) {
            const url = new URL(location.href);
            for (const [key, value] of Object.entries({ q: this.query, conference: this.conference === 'all' ? '' : this.conference, year: this.year })) {
                if (value) url.searchParams.set(key, value);
                else url.searchParams.delete(key);
            }
            if (url.href !== location.href) history.pushState(null, '', url);
        }
    },
}));

/**
 * Theme. The inline script in <head> sets the class before paint; this only
 * handles the toggle and remembers the choice.
 */
Alpine.data('theme', () => ({
    dark: document.documentElement.classList.contains('dark'),

    init() {
        matchMedia('(prefers-color-scheme: dark)').addEventListener('change', event => {
            let saved;
            try { saved = localStorage.getItem('theme'); } catch {}
            if (!saved) {
                this.dark = event.matches;
                document.documentElement.classList.toggle('dark', this.dark);
            }
        });
    },

    toggle() {
        this.dark = !this.dark;
        document.documentElement.classList.toggle('dark', this.dark);
        try { localStorage.setItem('theme', this.dark ? 'dark' : 'light'); } catch {}
    },
}));

Alpine.start();
