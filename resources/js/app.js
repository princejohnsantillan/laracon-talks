import Alpine from 'alpinejs';
import stemmer from 'wink-porter2-stemmer';

window.Alpine = Alpine;

const wordsIn = (value) => value.toLowerCase().normalize('NFKD')
    .replace(/\p{Diacritic}/gu, '').replace(/ł/g, 'l').replace(/ø/g, 'o').replace(/ß/g, 'ss')
    .replace(/['’]s\b/gu, '').replace(/['’]/gu, '').match(/[\p{L}\p{N}]+/gu) ?? [];

// Preserve short technical terms such as iOS instead of stemming them to "io".
const stemWord = (word) => word.length <= 3 ? word : stemmer(word);

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
    announced: total,
    settled: true,
    feedbackTimer: null,
    searchHistory: false,
    rows: [],
    tickRows: [],
    ledgerOpen: false,
    yearFocus: null,
    chipsBefore: false,
    chipsAfter: false,
    conferenceCounts: {},
    yearCounts: {},

    init() {
        // The row's own text is the search index — year, conference, title,
        // speaker and summary are all already on screen.
        this.rows = Array.from(this.$root.querySelectorAll('tbody tr[data-conference]')).map((el) => {
            const words = new Set(wordsIn(el.textContent));
            const speakers = el.querySelector('.cell-speaker').textContent;
            const speakerWords = wordsIn(speakers);
            speakers.split(/\s*(?:,| and | & )\s*/).forEach(name => words.add(wordsIn(name).join('')));
            return {
                el,
                words,
                speakerWords,
                stems: new Set([...words].map(stemWord)),
                conference: el.dataset.conference,
                year: el.dataset.year,
            };
        });

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
        new ResizeObserver(() => this.measureChips()).observe(this.$root.querySelector('.chips'));

        this.readUrl();
        this.apply(false);
        window.addEventListener('popstate', () => {
            this.readUrl();
            this.apply(false);
            this.$nextTick(() => this.searchHistory = false);
        });
        this.$watch('query', () => {
            // One entry per editing session, not one per debounced keystroke.
            this.apply(!this.searchHistory);
            this.searchHistory = true;
        });
        this.$watch('conference', () => this.apply());
        this.$watch('year', () => this.apply());
    },

    readUrl() {
        const params = new URLSearchParams(location.search);
        this.query = params.get('q') ?? '';
        this.conference = this.rows.some(row => row.conference === params.get('conference'))
            ? params.get('conference') : 'all';
        this.year = this.rows.some(row => row.year === params.get('year')) ? params.get('year') : null;
        this.yearFocus = this.year;
    },

    get filtered() {
        return this.query !== '' || this.conference !== 'all' || this.year !== null;
    },

    setConference(slug) {
        this.searchHistory = false;
        this.conference = this.conference === slug ? 'all' : slug;
    },

    revealTalk(event) {
        if (!event.target.matches('.talk-link:focus-visible')) return;
        const row = event.target.closest('tr');
        const clearance = parseFloat(getComputedStyle(row).scrollMarginTop);
        if (event.target.getBoundingClientRect().top < clearance) {
            row.scrollIntoView({ block: 'start', behavior: 'instant' });
        }
    },

    revealChip(chip) {
        if (!chip) return;
        const strip = chip.parentElement;
        // Horizontal only: restoring filters must not move the page vertically.
        strip.scrollLeft = chip.offsetLeft - (strip.clientWidth - chip.offsetWidth) / 2;
        this.measureChips();
    },

    measureChips() {
        const strip = this.$root.querySelector('.chips');
        this.chipsBefore = strip.scrollLeft > 1;
        this.chipsAfter = strip.scrollLeft + strip.clientWidth < strip.scrollWidth - 1;
    },

    focusYear(event) {
        const options = this.tickRows.map(row => row.el).filter(el => !el.disabled);
        const current = options.indexOf(document.activeElement);
        let index = current + (event.key === 'ArrowDown' ? 1 : -1);
        if (event.key === 'Home') index = 0;
        if (event.key === 'End') index = options.length - 1;
        options[(index + options.length) % options.length]?.focus();
    },

    toggleYear(year) {
        this.searchHistory = false;
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
        this.searchHistory = false;
        this.query = '';
        this.conference = 'all';
        this.year = null;
        const target = matchMedia('(pointer: coarse)').matches
            ? this.$root.querySelector('.controls') : this.$refs.search;
        target.focus({ preventScroll: true });
    },

    /** Focus search on "/" the way a code search would. */
    focusSearch(event) {
        if (document.activeElement?.matches('input, textarea, select, [contenteditable]') ||
            event.ctrlKey || event.metaKey || event.altKey) {
            return;
        }

        event.preventDefault();
        this.$refs.search.focus({ preventScroll: true });
        this.$root.querySelector('.controls').scrollIntoView({ block: 'start', behavior: 'instant' });
    },

    apply(updateUrl = true) {
        const terms = wordsIn(this.query).map(raw => ({ raw, stem: stemWord(raw) }));
        const perYear = new Map();
        const perConference = { all: 0 };
        let shown = 0;
        let resultsChanged = false;

        for (const row of this.rows) {
            const matchesQuery = terms.every(({ raw, stem }) => {
                // Short topics such as AI must not match words like "maintain".
                if (raw.length <= 2) return row.words.has(raw) || (raw.length === 2 &&
                    row.speakerWords.some(word => word.startsWith(raw)));
                // Complete raw words too: "eloquen" is longer than Eloquent's stem.
                // Match only word beginnings, never internal substrings.
                return row.stems.has(stem) || [...row.words].some(word => word.startsWith(raw)) ||
                    (stem.endsWith('e') && row.words.has(`${stem.slice(0, -1)}ing`)) ||
                    (raw.length >= 4 && stem.length >= 4 &&
                    [...row.stems].some(word => word.startsWith(stem)));
            });
            const matchesConference = this.conference === 'all' || row.conference === this.conference;
            const matchesYear = this.year === null || row.year === this.year;
            const match = matchesQuery && matchesConference && matchesYear;
            const wasShown = !row.el.classList.contains('row-hidden');
            if (match !== wasShown) resultsChanged = true;
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
        this.yearCounts = Object.fromEntries(perYear);
        this.settled = false;
        clearTimeout(this.feedbackTimer);
        this.feedbackTimer = setTimeout(() => {
            this.announced = this.shown;
            this.settled = true;
            // x-show reveals the empty state on the next animation frame.
            this.$nextTick(() => requestAnimationFrame(() => {
                if (document.activeElement !== this.$refs.search || !(resultsChanged || this.shown === 0)) return;
                const feedback = this.shown > 0
                    ? this.rows.find(row => !row.el.classList.contains('row-hidden')).el.querySelector('.talk-link')
                    : this.$root.querySelector('.empty h2');
                const bounds = feedback.getBoundingClientRect();
                const controls = this.$root.querySelector('.controls');
                if (bounds.top >= controls.getBoundingClientRect().bottom && bounds.bottom > innerHeight) {
                    controls.scrollIntoView({ block: 'start', behavior: 'instant' });
                }
            }));
        }, 400);

        for (const { el, year, ticks } of this.tickRows) {
            const lit = perYear.get(year) ?? 0;
            el.querySelector('.ledger-count').textContent = lit;
            el.setAttribute('aria-label', `Filter by ${year} — ${lit} matching ${lit === 1 ? 'talk' : 'talks'}`);
            el.disabled = lit === 0 && this.year !== year;
            ticks.forEach((tick, index) => tick.classList.toggle('lit', index < lit));
        }
        if (!this.tickRows.some(row => row.year === this.yearFocus && !row.el.disabled)) {
            this.yearFocus = this.tickRows.find(row => !row.el.disabled)?.year ?? null;
        }

        const url = new URL(location.href);
        for (const [key, value] of Object.entries({ q: this.query, conference: this.conference === 'all' ? '' : this.conference, year: this.year })) {
            if (value) url.searchParams.set(key, value);
            else url.searchParams.delete(key);
        }
        // Invalid shared URL facets are removed without adding a history entry.
        if (url.href !== location.href) history[updateUrl ? 'pushState' : 'replaceState'](null, '', url);
        this.$nextTick(() => {
            this.revealChip(this.$root.querySelector('.chip[aria-pressed="true"]'));
            if (resultsChanged || shown === 0) {
                const top = this.$root.querySelector('#talks').getBoundingClientRect().top;
                const bottom = this.$root.querySelector('.controls').getBoundingClientRect().bottom;
                if (top < bottom) window.scrollTo({ top: scrollY + top - bottom, behavior: 'instant' });
            }
        });
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
