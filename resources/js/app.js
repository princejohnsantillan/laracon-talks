import Alpine from 'alpinejs';

window.Alpine = Alpine;

const prefersReducedMotion = () => matchMedia('(prefers-reduced-motion: reduce)').matches;
const scrollBehavior = () => (prefersReducedMotion() ? 'auto' : 'smooth');

/**
 * Lowercase, drop accents and turn punctuation into spaces, padded with a
 * space on each side so a term can be matched at the start of any word.
 * "&" reads as "and", so "devs and depression" finds "Devs & Depression".
 */
const normalize = (text) =>
    ` ${text
        .replace(/&/g, ' and ')
        .normalize('NFKD')
        .replace(/\p{M}/gu, '')
        .toLowerCase()
        .replace(/[^\p{L}\p{N}]+/gu, ' ')
        .trim()} `;

// Talks with some, but not all, of the searched words are listed only when fewer than
// PARTIAL_BELOW talks have them all, and at most PARTIAL_LIMIT of them.
const PARTIAL_BELOW = 3;
const PARTIAL_LIMIT = 12;
// A searched word in the title, speakers or event of at most this many talks is specific
// enough that those talks are listed even when the search's other words are missing.
const RARE_IN_HEADLINE = 12;

/**
 * Words that only join others. A search with other words ignores them, so "jiro dreams of sushi"
 * needs jiro, dreams and sushi, and "of" is neither highlighted nor offered on its own. A search
 * of nothing else ("the") keeps them.
 */
const JOINING_WORDS = new Set(['a', 'an', 'and', 'the', 'of', 'on', 'in', 'to', 'for', 'at', 'by', 'with', 'from', 'or', 'as', 'is', 'are', 'was', 'be', 'it', 'its', 'into', 'vs', 'via']);

// Each word once: "eloquent eloquent mockery" is the same search as "eloquent mockery".
const searchTerms = (query) => {
    const words = [...new Set(normalize(query).trim().split(' ').filter(Boolean))];
    const meaningful = words.filter((word) => !JOINING_WORDS.has(word));

    return meaningful.length > 0 ? meaningful : words;
};

/**
 * Parts of three letters or fewer that a searched word was split into at a hyphen or dot
 * ("ups" in "push-ups", "js" in "react.js"). Captions are indexed word by word, so whether
 * a talk said "push-ups" can't be told from it saying "ups" somewhere; such parts have to be
 * on the card or among the keywords.
 */
const splitParts = (query) =>
    new Set(
        query
            .split(/\s+/)
            .map((word) => normalize(word).trim().split(' '))
            .filter((parts) => parts.length > 1)
            .flat()
            .filter((part) => part.length <= 3),
    );

/**
 * One- and two-letter terms ("AI", "UX") match whole words only, so "ai" doesn't
 * find "aims" or "airport". Longer terms match the start of words: "live" finds Livewire.
 */
const isShort = (term) => term.length <= 2;

/**
 * The start every form of a word of five or more letters shares, so "organizes" also finds
 * "organize" and "organization", "isolated" finds "isolation" and "libraries" finds "library".
 * Null when the word has no such ending or the stem would be shorter than four letters.
 */
const stemOf = (term) => {
    if (term.length < 5) {
        return null;
    }

    let stem = null;

    if (term.endsWith('ies')) {
        stem = `${term.slice(0, -3)}y`;
    } else if (/ations?$/.test(term)) {
        stem = term.replace(/ations?$/, '');
    } else if (term.endsWith('ing') || term.endsWith('ed')) {
        stem = term.replace(/(ing|ed)$/, '');
    } else if (term.endsWith('s') && !/(ss|us|is)$/.test(term)) {
        stem = term.endsWith('es') && term.length - 2 >= 4 ? term.slice(0, -2) : term.slice(0, -1);
    }

    return stem && stem.length >= 4 ? stem : null;
};

/**
 * The endings a stem may take: "organiz" + "ation" is "organization" and "test" + "ing" is
 * "testing", but "organis" + "m" (organism) and "test" + "imonials" are other words.
 */
const ENDINGS = '(?:e|es|s|ed|er|ers|ing|ings|ion|ions|ation|ations|y|ies|ied|ize|izes|ized|izing|ise|ises|ised|ising)?';

/**
 * How a searched word matches: the word and its other English spelling ("optimisation" also
 * finds "optimization", and "authorize" finds "authorise") at the start of any word, and the
 * stem of each as a whole word with one of the ENDINGS (`stem`, a pattern for a normalized text).
 */
const forms = new Map();
const formsOf = (term) => {
    if (!forms.has(term)) {
        const other = term.replace(/i([sz])(ations?|ing|ed|es|e)$/, (match, letter, ending) => `i${letter === 's' ? 'z' : 's'}${ending}`);
        const words = [...new Set([term, other])];
        const stems = [...new Set(words.map(stemOf).filter(Boolean))];
        const stem = stems.length > 0 ? new RegExp(` (?:${stems.map(escapeRegExp).join('|')})${ENDINGS} `) : null;

        forms.set(term, { words, stems, stem });
    }

    return forms.get(term);
};

/**
 * The words and stems /said is asked about for a searched word: the word, its other spelling and
 * their stems, without any that a shorter one already covers ("organiz" covers "organizes").
 * Words of one or two letters have none: the captions index words of three or more letters.
 */
const saidStartsOf = (term) => {
    const { words, stems } = formsOf(term);
    const starts = [...new Set([...words, ...stems])].filter((start) => /^[a-z0-9]{3,40}$/.test(start));

    return starts.filter((start) => !starts.some((other) => other !== start && start.startsWith(other)));
};

/**
 * Whether a word said in a talk is a form of a searched word: the word, its other spelling or their
 * stems, with one of the ENDINGS, as a whole word. Stricter than termMatches(), which lets a word
 * match the start of a longer one ("live" finds Livewire): across everything said in 626 talks,
 * "ups" would find "upstream" and "test" "testimonials".
 */
const heardForms = new Map();
const heardMatches = (word, term) => {
    if (!heardForms.has(term)) {
        const { words, stems } = formsOf(term);

        heardForms.set(term, new RegExp(`^(?:${[...words, ...stems].map(escapeRegExp).join('|')})${ENDINGS}$`));
    }

    return heardForms.get(term).test(word);
};

const termMatches = (haystack, term) => {
    const { words, stem } = formsOf(term);

    return words.some((word) => haystack.includes(isShort(word) ? ` ${word} ` : ` ${word}`)) || (stem !== null && stem.test(haystack));
};

/**
 * Every word matches, or the words written together match: "native php" finds NativePHP
 * and "live wire" finds Livewire.
 */
const matchesAll = (haystack, terms) =>
    terms.every((term) => termMatches(haystack, term)) || (terms.length > 1 && termMatches(haystack, terms.join('')));

/**
 * How closely a best match fits the search, lowest first: the exact title, then the
 * words in the order typed (or written together), then the words anywhere.
 */
const closeness = (row, terms, typed) => {
    if (terms.length === 0) {
        return 2;
    }

    // As typed, joining words included: "the tao of laravel" is that title exactly.
    if (row.title === typed || row.title === ` ${terms.join(' ')} `) {
        return 0;
    }

    return termMatches(row.headline, typed.trim()) || termMatches(row.headline, terms.join(' ')) || (terms.length > 1 && termMatches(row.headline, terms.join('')))
        ? 1
        : 2;
};

/**
 * Move focus to a heading, card or section that is not a control. The tabindex is
 * added only while it has focus: a permanently focusable heading is announced as
 * "clickable" by screen readers such as Orca.
 */
const focusQuietly = (el) => {
    if (!el.matches('a[href], button, input, select, textarea, [tabindex]')) {
        el.setAttribute('tabindex', '-1');
        el.addEventListener('blur', () => el.removeAttribute('tabindex'), { once: true });
    }

    el.focus({ preventScroll: true });
};

const escapeRegExp = (text) => text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

/**
 * Edit distance where a swapped pair of letters counts as one edit ("pset" → "pest"),
 * with an early exit once every path exceeds `max`.
 */
const distance = (a, b, max) => {
    if (Math.abs(a.length - b.length) > max) {
        return max + 1;
    }

    let before = null;
    let previous = Array.from({ length: b.length + 1 }, (_, index) => index);

    for (let i = 1; i <= a.length; i++) {
        const current = [i];
        let best = i;

        for (let j = 1; j <= b.length; j++) {
            let cost = Math.min(previous[j] + 1, current[j - 1] + 1, previous[j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1));

            if (before && j > 1 && a[i - 1] === b[j - 2] && a[i - 2] === b[j - 1]) {
                cost = Math.min(cost, before[j - 2] + 1);
            }

            current[j] = cost;
            best = Math.min(best, cost);
        }

        if (best > max) {
            return max + 1;
        }

        before = previous;
        previous = current;
    }

    return previous[b.length];
};

/**
 * Card thumbnails fade in once they arrive. Listening on the document in the
 * capture phase catches every image, including ones that load before Alpine.
 */
document.addEventListener(
    'load',
    (event) => {
        if (event.target instanceof HTMLImageElement && event.target.closest('.card-media')) {
            event.target.classList.add('is-loaded');
        }
    },
    true,
);

// Module scripts run after parsing, so images that finished earlier never fire
// a load event we can hear; mark those directly.
document.querySelectorAll('.card-media img').forEach((img) => {
    if (img.complete && img.naturalWidth > 0) img.classList.add('is-loaded');
});

document.addEventListener(
    'error',
    (event) => {
        // A missing thumbnail leaves the conference/year placeholder showing.
        if (event.target instanceof HTMLImageElement && event.target.closest('.card-media')) {
            event.target.remove();
        }
    },
    true,
);

/**
 * The archive.
 *
 * Blade renders the talks. An unfiltered visit gets the newest years first and
 * `earlier` (a URL) for the rest, which are added right after load. A filtered
 * link gets every year at once. Alpine only hides and shows what is there, keeps
 * the URL, the ledger, the counts and the year headings in step, and highlights matches.
 */
Alpine.data('archive', ({ total, strings, repository, conferences, years, languages, locale, earlier, keywords: keywordsUrl, said: saidUrl }) => {
    // Plain closure state: DOM references and timers never need to be reactive.
    let rows = [];
    let tickCols = [];
    let groups = [];
    let facets = new Map();
    let yearOptions = new Map();
    let marked = [];
    let vocabulary = null;
    let speakerNames = null;
    let pendingHistory = null;
    let queued = false;
    let keepUndo = false;
    let announceTimer = null;
    let toastTimer = null;
    let initialised = false;
    // The component's root and its x-ref elements, kept here because $root and $refs
    // resolve to the nearest template copy when a method runs from inside x-if or x-for.
    let root = null;
    let refs = {};
    let summaryObserver = null;
    // Each year's list of cards and its rows in page order, to put moved cards back in place.
    const homes = new Map();
    // The ranked search results (see rank()), and the rows whose cards are in them.
    let ranked = null;
    let moved = new Set();
    // Year lists that cards have left: browsing puts their cards back in order.
    const disturbed = new Set();
    // While the earlier years are loading, a promise that settles once they are in the page.
    let loading = null;
    // The words each talk says (from its captions), by permalink, once they have arrived;
    // and while they load, a promise that settles when they are attached to the rows.
    let keywordMap = null;
    let keywordsLoading = null;
    // What /said answered, by word start: { forms: { word: { permalink: times said } } }, or
    // 'common' (said in nearly every talk) or 'many' (begins too many words to narrow anything).
    const saidByStart = new Map();
    // The /said request on its way, if any: its word starts and a way to cancel it.
    let saidRequest = null;
    // For the search shown: per searched word, the talks that say it (see heardFor), or null.
    let heard = null;
    // Rows whose card shows the "Said in the talk" line.
    const saying = new Set();
    // Where the results start and how far the page is scrolled, measured only while the
    // layout is clean (on scroll frames and resizes), so a filter change never forces one.
    let measured = { top: 0, scrollY: 0 };

    const lang = document.documentElement.lang || undefined;
    const numbers = new Intl.NumberFormat(lang);
    const plurals = new Intl.PluralRules(lang);

    return {
        total,
        strings,
        repository,
        conferences,
        years,

        query: '',
        conference: 'all',
        year: '',
        speaker: '',

        shown: total,
        status: '',
        stuck: false,
        farDown: false,
        compact: false,
        narrowest: false,
        ledgerOpen: false,
        toast: null,
        undo: null,
        suggestion: null,
        corrections: [],
        eventMissing: false,
        // How many talks with some, but not all, of the searched words are shown, and how many have them.
        partial: 0,
        partialTotal: 0,
        // The years after the first two: 'loading', 'loaded' or 'failed' ('loaded' when the page has them all).
        earlierState: earlier ? 'loading' : 'loaded',
        // The words said in each talk (see loadKeywords): 'loading', 'loaded' or 'failed'.
        keywordsState: 'loading',
        // Everything said in the talks, asked of /said when few talks match (see loadSaid): 'idle',
        // 'loading' or 'failed'; and whether the search shown went without it because it failed.
        saidState: 'idle',
        saidMissing: false,
        saidCovered: false,
        // Some chips or years count talks with some of the words while the list shows full matches.
        mixedCounts: false,
        // A filter change is waiting for the earlier years (or a search for the words said in the
        // talks); until then the results are not filtered.
        pending: false,
        languageHint: null,

        init() {
            root = this.$root;
            refs = { search: this.$refs.search, sentinel: this.$refs.sentinel, toolbar: this.$refs.toolbar, undoButton: this.$refs.undoButton };

            const rankedEl = root.querySelector('[data-ranked]');

            ranked = {
                el: rankedEl,
                top: rankedEl.querySelector('[data-ranked-list="top"]'),
                topHeading: rankedEl.querySelector('[data-ranked-heading="top"]'),
                rest: rankedEl.querySelector('[data-ranked-list="rest"]'),
                restHead: rankedEl.querySelector('[data-ranked-divider="rest"]'),
                restCount: rankedEl.querySelector('[data-ranked-count="rest"]'),
                spoken: rankedEl.querySelector('[data-ranked-list="spoken"]'),
                spokenHead: rankedEl.querySelector('[data-ranked-divider="spoken"]'),
                spokenCount: rankedEl.querySelector('[data-ranked-count="spoken"]'),
                someEl: root.querySelector('[data-ranked-some]'),
                some: root.querySelector('[data-ranked-list="some"]'),
                someCount: root.querySelector('[data-ranked-count="some"]'),
            };

            this.addTalks(root);

            tickCols = Array.from(root.querySelectorAll('[data-tick-year]'), (el) => ({
                col: el,
                el,
                year: el.dataset.tickYear,
                ticks: el.querySelector('.ledger-ticks'),
                count: el.querySelector('[data-tick-count]'),
                label: el.querySelector('[data-tick-label]'),
            }));

            facets = new Map(Array.from(root.querySelectorAll('[data-facet]'), (el) => [el.dataset.facet, el]));
            yearOptions = new Map(Array.from(root.querySelectorAll('[data-year-option]'), (el) => [el.dataset.yearOption, el]));

            this.watchLayout();
            this.watchSummaries();

            const narrow = matchMedia('(max-width: 639px)');
            this.compact = narrow.matches;
            narrow.addEventListener('change', (event) => (this.compact = event.matches));

            // Below 390 px even the short placeholder is cut off (in Spanish from 389 px down); say just "Search".
            const narrowest = matchMedia('(max-width: 389px)');
            this.narrowest = narrowest.matches;
            narrowest.addEventListener('change', (event) => (this.narrowest = event.matches));

            // On a slow phone the field can be typed in before this runs; keep those words.
            const typed = refs.search.value.trim();
            const state = this.readUrl();

            if (typed && state.query === '') {
                state.query = typed;
            }

            Object.assign(this, state);

            keywordsLoading = this.loadKeywords();

            // The server's counts already describe the unfiltered archive; filtering waits for every
            // year, and a search (from the link or typed early) for the words said in the talks too.
            if (earlier) {
                loading = this.loadEarlier();
            }

            const searching = this.query.trim() !== '';

            if (!earlier && !searching) {
                this.apply();
            }

            initialised = true;

            if ((earlier && this.filtered) || searching) {
                this.queue('replace');
            }

            // Drop unknown ?conference= or ?year= values (and keep typed words) so the address bar
            // matches what is shown. A #permalink is left for revealHash to handle.
            this.writeUrl('replace', { keepHash: true });

            this.languageHint = this.findLanguageHint();

            this.$watch('query', () => {
                this.cancelStaleSaid();
                this.queue('replace');
            });
            this.$watch('conference', () => this.queue('push'));
            this.$watch('year', () => this.queue('push'));
            this.$watch('speaker', () => this.queue('push'));

            window.addEventListener('popstate', () => Object.assign(this, this.readUrl()));
            window.addEventListener('hashchange', () => this.revealHash());

            // A permalink opens at its talk; a link with filters opens at the results.
            requestAnimationFrame(() => {
                if (location.hash) {
                    this.revealHash();
                } else if (this.filtered && window.scrollY === 0) {
                    window.scrollTo({ top: this.resultsTop(), behavior: 'instant' });
                }
            });
        },

        /* ---------------------------------------------------------------
           Derived state
        --------------------------------------------------------------- */

        get filtered() {
            return this.query.trim() !== '' || this.conference !== 'all' || this.year !== '' || this.speaker !== '';
        },

        /** Searching (by words or by speaker) shows one flat grid; browsing keeps the year sections. */
        get flat() {
            return !this.pending && (this.query.trim() !== '' || this.speaker !== '');
        },

        /**
         * The results heading. Each combination of filters is its own translatable
         * sentence (keyed q, s, c, y), with a "zero" form that doubles as the empty-state heading.
         */
        get summary() {
            // Never a count for results that are not filtered yet.
            if (this.pending) {
                return this.t('searchingAll', { count: numbers.format(this.total) });
            }

            if (!this.filtered) {
                return this.t('showingAll', { count: numbers.format(this.total) });
            }

            const query = this.query.trim();
            const key = [query && 'q', this.speaker && 's', this.conference !== 'all' && 'c', this.year && 'y'].filter(Boolean).join('');
            const forms = this.strings.summaries[key];
            // No full match, but talks with some of the words: say both, with the count of those.
            if (this.shown === 0 && this.partial > 0 && forms.some) {
                return this.t(forms.some[plurals.select(this.partialTotal)] ?? forms.some.other, {
                    count: numbers.format(this.partialTotal),
                    query,
                    speaker: this.speaker,
                    conference: this.conferences[this.conference],
                    year: this.year,
                });
            }

            const form = this.shown === 0 ? 'zero' : plurals.select(this.shown);

            return this.t(forms[form] ?? forms.other, {
                count: numbers.format(this.shown),
                query,
                speaker: this.speaker,
                conference: this.conferences[this.conference],
                year: this.year,
            });
        },

        /** A search ran without the words said in the talks, because they failed to load. */
        get keywordsMissing() {
            return this.keywordsState === 'failed' && !this.pending && this.query.trim() !== '' && !this.saidCovered;
        },

        get onlyQuery() {
            return this.query.trim() !== '' && this.conference === 'all' && this.year === '' && this.speaker === '';
        },

        /** "Clear search" when a search is the only thing narrowing the list. */
        get clearLabel() {
            return this.onlyQuery ? this.strings.clearSearch : this.strings.clearFilters;
        },

        /** The current filters as a query string, shared by the address bar and the language links. */
        get searchString() {
            const params = new URLSearchParams();
            const query = this.query.trim();

            if (query) params.set('q', query);
            if (this.conference !== 'all') params.set('conference', this.conference);
            if (this.year) params.set('year', this.year);
            if (this.speaker) params.set('speaker', this.speaker);

            return params.toString();
        },

        withFilters(url) {
            return this.searchString ? `${url}?${this.searchString}` : url;
        },

        get reportUrl() {
            const query = this.query.trim();

            return query
                ? `${this.repository}/issues/new?title=${encodeURIComponent(this.t('missingTalk', { query }))}`
                : `${this.repository}/issues/new`;
        },

        isTopic(topic) {
            return this.query.trim().toLowerCase() === topic.toLowerCase();
        },

        /* ---------------------------------------------------------------
           Actions
        --------------------------------------------------------------- */

        setConference(slug) {
            this.conference = this.conference === slug || slug === 'all' ? 'all' : slug;
        },

        /** Ledger years toggle. A pointer click on an off-screen result list scrolls to it; keyboard focus stays put. */
        toggleYear(year, event = null) {
            this.year = this.year === year ? '' : year;

            if (event?.detail > 0) {
                this.$nextTick(() => this.revealResultsIfHidden());
            }
        },

        /** Topic buttons toggle like chips. From the empty state they also drop the filters that caused it. */
        searchFor(topic, widen = false) {
            pendingHistory = 'push';

            if (widen) {
                this.conference = 'all';
                this.year = '';
                this.speaker = '';
            } else if (this.isTopic(topic)) {
                this.query = '';

                return;
            }

            this.query = topic;
            this.$nextTick(() => this.revealResults());
        },

        /** "Show all talks by …": every talk by that person, whatever was filtered before. Back restores it. */
        showSpeaker(name) {
            this.query = '';
            this.conference = 'all';
            this.year = '';
            this.speaker = name;
            this.$nextTick(() => this.revealResults());
        },

        applySuggestion() {
            if (this.suggestion) {
                Object.assign(this, this.suggestion.state);
                this.$nextTick(() => this.revealResults());
            }
        },

        clearQuery() {
            this.query = '';
            refs.search.focus();
        },

        /** Clear everything, and keep an Undo right where "Clear all" was until the next change. */
        clearAll() {
            const previous = this.snapshot();
            const message = this.onlyQuery ? this.strings.searchCleared : this.strings.cleared;

            this.query = '';
            this.conference = 'all';
            this.year = '';
            this.speaker = '';

            keepUndo = true;
            this.undo = { message, state: previous };
            this.focusWhenShown(refs.undoButton);
        },

        /** x-show can reveal an element a frame or two after the state changes; focus it once it is rendered. */
        focusWhenShown(el, frames = 10) {
            const attempt = (left) => {
                if (el?.offsetParent) {
                    el.focus({ preventScroll: true });
                } else if (left > 0) {
                    requestAnimationFrame(() => attempt(left - 1));
                }
            };

            this.$nextTick(() => attempt(frames));
        },

        runUndo() {
            const state = this.undo?.state;

            this.undo = null;

            if (state) {
                Object.assign(this, state);
                refs.search.focus({ preventScroll: true });
            }
        },

        onSearchEscape(event) {
            if (this.query !== '') {
                event.preventDefault();
                this.query = '';
            } else {
                refs.search.blur();
            }
        },

        /** Typing with the results below the fold (a phone, at the top of the page) pins the search bar so they show. */
        onSearchInput() {
            if (this.stuck) {
                return;
            }

            const heading = document.getElementById('results-title').getBoundingClientRect();

            if (heading.top > window.innerHeight - 160) {
                window.scrollTo({ top: this.resultsTop(), behavior: scrollBehavior() });
            }
        },

        /** "/" or Cmd/Ctrl+K jumps to search, the way a code search would. */
        onKeydown(event) {
            if (event.defaultPrevented || event.isComposing) {
                return;
            }

            const typing = event.target.closest?.('input, textarea, select, [contenteditable="true"]');
            const slash = event.key === '/' && !typing && !event.metaKey && !event.ctrlKey && !event.altKey;
            const commandK = event.key.toLowerCase() === 'k' && (event.metaKey || event.ctrlKey);

            if (slash || commandK) {
                event.preventDefault();
                this.jumpToSearch();
                refs.search.select();
            }
        },

        /** The ledger is one tab stop; arrow keys, Home and End move between years. */
        onLedgerKeydown(event) {
            const cols = tickCols.map(({ el }) => el);
            const index = cols.indexOf(document.activeElement);

            if (index === -1) {
                return;
            }

            const rtl = getComputedStyle(event.currentTarget).direction === 'rtl';
            const step = { ArrowRight: rtl ? -1 : 1, ArrowLeft: rtl ? 1 : -1, ArrowDown: 1, ArrowUp: -1 }[event.key];
            let next = null;

            if (step !== undefined) {
                next = Math.min(cols.length - 1, Math.max(0, index + step));
            } else if (event.key === 'Home') {
                next = 0;
            } else if (event.key === 'End') {
                next = cols.length - 1;
            }

            if (next === null) {
                return;
            }

            event.preventDefault();
            cols.forEach((col, i) => (col.tabIndex = i === next ? 0 : -1));
            cols[next].focus();
        },

        /** One listener for every card's speaker, "Show more" and copy-link buttons. */
        onResultsClick(event) {
            const button = event.target.closest?.('.card [data-action]');

            if (!button) {
                return;
            }

            if (button.dataset.action === 'speaker') {
                this.showSpeaker(button.dataset.speaker);
            } else if (button.dataset.action === 'more') {
                this.toggleSummary(button);
            } else if (button.dataset.action === 'copy') {
                this.copyLink(button.closest('[data-talk]').id);
            }
        },

        toggleSummary(button) {
            const summary = document.getElementById(button.getAttribute('aria-controls'));
            const open = summary.classList.toggle('is-open');

            button.setAttribute('aria-expanded', String(open));
            button.textContent = open ? this.strings.less : this.strings.more;
        },

        /** Permalinks are clean (no filters), so the talk is always visible when the link is opened. */
        async copyLink(anchor) {
            const url = `${location.origin}${location.pathname}#${anchor}`;

            try {
                await navigator.clipboard.writeText(url);
                this.notify(this.strings.copied);
            } catch {
                history.replaceState(history.state, '', `${location.pathname}${location.search}#${anchor}`);
                this.notify(this.strings.copyFailed);
            }
        },

        jumpToSearch() {
            window.scrollTo({ top: this.resultsTop(), behavior: scrollBehavior() });
            refs.search.focus({ preventScroll: true });
        },

        notify(message) {
            clearTimeout(toastTimer);
            this.toast = message;
            toastTimer = setTimeout(() => (this.toast = null), 3500);
        },

        /* ---------------------------------------------------------------
           Filtering
        --------------------------------------------------------------- */

        /** Batch every change made in one tick into a single filter pass and history entry. */
        queue(mode) {
            pendingHistory = pendingHistory === 'push' ? 'push' : mode;

            if (queued) {
                return;
            }

            queued = true;

            const run = () => {
                // Without every year a filter would miss talks; the full page renders them all.
                if (this.earlierState === 'failed' && this.filtered) {
                    location.assign(`${location.pathname}?${this.searchString}`);

                    return;
                }

                const historyMode = pendingHistory;

                queued = false;
                pendingHistory = null;
                this.pending = false;

                if (!keepUndo) {
                    this.undo = null;
                }

                keepUndo = false;

                // Use the last clean measurement: reading the layout here, after Alpine has
                // already restyled the results, would force an extra full layout.
                const { top } = measured;
                const scrolledPast = measured.scrollY > top + 1;

                this.apply();
                this.writeUrl(historyMode);

                // A reader deep in the list starts again at the top of the new results.
                if (scrolledPast) {
                    window.scrollTo({ top, behavior: 'instant' });
                }

                this.announce();
            };

            // When few talks match, ask which talks say the words anywhere in them, then look again:
            // the words may have changed while the answer was on its way.
            const proceed = () => {
                const said = this.saidNeeded();

                if (said === null) {
                    run();

                    return;
                }

                if (!this.pending) {
                    this.pending = true;
                    this.announce();
                }

                said.then(proceed);
            };

            // Every year has to be in the page, and a search also needs the words said in the talks.
            const waiting = [loading, this.query.trim() !== '' ? keywordsLoading : null].filter(Boolean);

            if (waiting.length > 0) {
                // Say so, rather than show a count for cards that are not filtered yet.
                this.pending = true;
                this.announce();
                Promise.all(waiting).then(proceed);
            } else {
                queueMicrotask(proceed);
            }
        },

        /** Index the talks inside `root` (the page, or a newly added year) for filtering. */
        addTalks(root) {
            const within = (selector) => [...(root.matches?.(selector) ? [root] : []), ...root.querySelectorAll(selector)];

            within('[data-talk]').forEach((card) => {
                const indexed = Array.from(card.querySelectorAll('[data-index]'));
                const item = card.closest('li');
                const row = {
                    card,
                    item,
                    // The year's list, where the card lives whenever it is not in the ranked search results.
                    home: item.parentElement,
                    haystack: normalize(indexed.map((el) => el.textContent).join(' ')),
                    // Everything but the summary: a search whose words are all here ranks first.
                    headline: normalize(indexed.filter((el) => !el.hasAttribute('data-summary')).map((el) => el.textContent).join(' ')),
                    title: normalize(card.querySelector('.card-title').textContent),
                    marks: Array.from(card.querySelectorAll('[data-mark]'), (el) => ({ el, text: el.textContent })),
                    // What is said in the talk, once the keywords arrive: its words, and the haystack with them.
                    words: [],
                    all: null,
                    said: null,
                    conference: card.dataset.conference,
                    year: card.dataset.year,
                    speakers: card.dataset.speakers.split('|'),
                };

                row.all = row.haystack;
                this.attachKeywords(row);
                rows.push(row);

                if (!homes.has(row.home)) {
                    homes.set(row.home, []);
                }

                homes.get(row.home).push(row);
            });

            within('[data-group-year]').forEach((el) =>
                groups.push({ el, year: el.dataset.groupYear, count: el.querySelector('[data-group-count]') }),
            );

            within('[data-summary]').forEach((summary) => summaryObserver?.observe(summary));

            // One listener per list of cards, for their speaker, Show more and Copy link buttons.
            // Not on the results section or the document: Chromium gives everything under a click
            // listener a click action, and Orca then reads headings as "clickable".
            within('.cards').forEach((list) => list.addEventListener('click', (event) => this.onResultsClick(event)));
        },

        /**
         * Fetch the words each talk says (the <head> preloads them). A search typed before they
         * arrive waits for them; a search already shown is run again. Without them, search
         * still covers titles, speakers, events and summaries.
         */
        async loadKeywords() {
            this.keywordsState = 'loading';

            try {
                const response = await fetch(keywordsUrl);

                if (!response.ok) {
                    throw new Error(`${response.status}`);
                }

                keywordMap = await response.json();
                this.keywordsState = 'loaded';
            } catch {
                // Search still covers titles, speakers, events and summaries; the results say what is missing.
                keywordMap = null;
                this.keywordsState = 'failed';
            }

            rows.forEach((row) => this.attachKeywords(row));
            keywordsLoading = null;

            if (initialised && !queued && !loading && this.query.trim() !== '') {
                const before = this.shown;

                this.apply();

                if (this.shown !== before) {
                    this.announce();
                }
            }
        },

        /**
         * A request to /said when the search needs it: fewer than PARTIAL_BELOW talks have every word on
         * the card or among their keywords, and some word's starts haven't been asked about yet. Null
         * otherwise, including after a failed request (the notice offers Try again).
         */
        saidNeeded() {
            const terms = searchTerms(this.query);

            // Also when the keywords failed to load: everything said includes them.
            if (this.keywordsState === 'loading' || this.saidState === 'failed' || terms.length === 0) {
                return null;
            }

            const split = splitParts(this.query);
            const missing = [...new Set(terms.filter((term) => !split.has(term)).flatMap(saidStartsOf))].filter((start) => this.saidEntry(start) === undefined);

            if (missing.length === 0 || rows.filter((row) => matchesAll(row.all, terms)).length >= PARTIAL_BELOW) {
                return null;
            }

            return this.loadSaid(missing.slice(0, 12));
        },

        /** What /said answered for a word start, or for a shorter start that covers it; undefined if not asked. */
        saidEntry(start) {
            for (let length = start.length; length >= 3; length--) {
                const entry = saidByStart.get(start.slice(0, length));

                // A shorter start's words include every word with this start, unless it was 'many' or 'common'.
                if (entry !== undefined && (length === start.length || typeof entry === 'object')) {
                    return entry;
                }
            }

            return undefined;
        },

        /**
         * Ask /said which talks say words with these starts anywhere in them. The searched words go in
         * the request body, not the address, and the server keeps nothing.
         */
        async loadSaid(starts) {
            const controller = new AbortController();

            this.saidState = 'loading';
            saidRequest = { starts, controller };

            try {
                const response = await fetch(saidUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                    body: JSON.stringify({ words: starts }),
                    signal: controller.signal,
                });

                if (!response.ok) {
                    throw new Error(`${response.status}`);
                }

                const { said, common, many } = await response.json();

                for (const start of starts) {
                    saidByStart.set(start, common.includes(start) ? 'common' : many.includes(start) ? 'many' : { forms: {} });
                }

                for (const [word, talks] of Object.entries(said)) {
                    for (const start of starts) {
                        if (word.startsWith(start) && typeof saidByStart.get(start) === 'object') {
                            saidByStart.get(start).forms[word] = talks;
                        }
                    }
                }

                this.saidState = 'idle';
            } catch {
                // Cancelled because the search changed (see cancelStaleSaid): not a failure.
                this.saidState = controller.signal.aborted ? 'idle' : 'failed';
            }

            saidRequest = null;
        },

        /** Cancel a /said request the search no longer needs, so the new one starts at once. */
        cancelStaleSaid() {
            if (saidRequest === null) {
                return;
            }

            const split = splitParts(this.query);
            const needed = new Set(searchTerms(this.query).filter((term) => !split.has(term)).flatMap(saidStartsOf));

            if (!saidRequest.starts.every((start) => needed.has(start))) {
                saidRequest.controller.abort();
            }
        },

        /** Try again to search everything said in the talks, as retryKeywords() does for the keywords. */
        retrySaid() {
            this.saidState = 'idle';
            this.queue('replace');
            focusQuietly(document.getElementById('results-title'));
        },

        /**
         * For each searched word, the talks that say it anywhere: a Map from permalink to the word heard
         * most and how often, 'all' for a word said in nearly every talk, or null when only the card and
         * keywords can match it: a word of one or two letters ("ai", which the captions don't index), a
         * short part of a split word (`split`, see splitParts) or a start too short to narrow anything.
         * Null overall unless every word's starts have been answered and at least one word narrows the search.
         */
        heardFor(terms, split) {
            const perTerm = [];

            for (const term of terms) {
                const starts = split.has(term) ? [] : saidStartsOf(term);

                if (starts.length === 0) {
                    perTerm.push(null);
                    continue;
                }

                const entries = starts.map((start) => this.saidEntry(start));

                if (entries.includes(undefined)) {
                    return null;
                }

                if (entries.includes('common')) {
                    perTerm.push('all');
                    continue;
                }

                if (entries.every((entry) => entry === 'many')) {
                    perTerm.push(null);
                    continue;
                }

                const talks = new Map();

                for (const entry of entries.filter((item) => typeof item === 'object')) {
                    for (const [word, counts] of Object.entries(entry.forms)) {
                        if (!heardMatches(word, term)) {
                            continue;
                        }

                        for (const [anchor, count] of Object.entries(counts)) {
                            const known = talks.get(anchor);

                            talks.set(anchor, {
                                word: known && known.best >= count ? known.word : word,
                                best: Math.max(known?.best ?? 0, count),
                                count: (known?.count ?? 0) + count,
                            });
                        }
                    }
                }

                perTerm.push(talks);
            }

            return perTerm.some((talks) => talks instanceof Map) ? perTerm : null;
        },

        /** Whether a talk says every searched word somewhere in it (or has it on its card). */
        saysAll(row, terms, perTerm) {
            return terms.every((term, index) => {
                const talks = perTerm[index];

                return talks === 'all' || (talks instanceof Map && talks.has(row.card.id)) || termMatches(row.all, term);
            });
        },

        /**
         * How strongly a talk is about the searched words: for each word, how rare it is, times how clearly
         * the talk has it: 1.5 in the title, speakers or event, 1 in the summary or keywords, and otherwise
         * more the more often it is said (once 0.45, three times 0.71, ten times 0.89).
         */
        heardScore(row, terms, perTerm) {
            return terms.reduce((score, term, index) => {
                const talks = perTerm[index];

                if (!(talks instanceof Map)) {
                    return score;
                }

                const times = talks.get(row.card.id)?.count ?? 0;
                const clearly = termMatches(row.headline, term) ? 1.5 : termMatches(row.all, term) ? 1 : times / (times + 1.2);

                return score + Math.log((rows.length + 1) / Math.max(talks.size, 1)) * clearly;
            }, 0);
        },

        attachKeywords(row) {
            const said = keywordMap?.[row.card.id];

            if (said) {
                row.words = said.split(' ');
                row.all = `${row.haystack}${normalize(said).slice(1)}`;
            }
        },

        /**
         * Fetch the years the first response left out (the <head> preloads them) and add them
         * one year per task, so typing and scrolling stay responsive. If this fails, the
         * placeholder offers Try again and a link to the full page, and a filter change or a
         * jump to an earlier year opens the full page instead.
         */
        async loadEarlier() {
            const container = root.querySelector('.groups');
            const placeholder = root.querySelector('[data-earlier]');

            this.earlierState = 'loading';

            try {
                // The same request as the preload in <head>, so the browser reuses its response.
                const response = await fetch(earlier);

                if (!response.ok) {
                    throw new Error(`${response.status}`);
                }

                const chunks = (await response.text()).split('<!--/year-->').filter((chunk) => chunk.trim() !== '');

                for (const chunk of chunks) {
                    await new Promise((resolve) => setTimeout(resolve, 0));

                    const template = document.createElement('template');
                    template.innerHTML = chunk;
                    const added = [...template.content.children];

                    // Index after adding: a ResizeObserver set up inside the inert template never reports.
                    container.append(template.content);
                    added.forEach((el) => this.addTalks(el));
                }

                placeholder?.remove();
                this.earlierState = 'loaded';
            } catch {
                this.earlierState = 'failed';
                this.status = this.strings.earlierFailed;
            }

            loading = null;
        },

        /**
         * Try again to load the words said in the talks. The notice and its button go away while
         * they load, so focus moves to the results heading, which says it is searching and then
         * gives the new count (also announced).
         */
        retryKeywords() {
            keywordsLoading = this.loadKeywords();
            this.queue('replace');
            focusQuietly(document.getElementById('results-title'));
        },

        /** Try again: say it is loading, then put focus on the first year that arrived. */
        retryEarlier() {
            const from = root.querySelector('[data-earlier] a')?.hash.slice(1);

            this.status = this.strings.loadingEarlier;
            loading = this.loadEarlier();
            loading.then(() => {
                if (this.earlierState === 'loaded') {
                    this.status = this.summary;
                    focusQuietly(document.getElementById(from)?.querySelector('h3') ?? root.querySelector('#results-title'));
                }
            });
        },

        apply() {
            const terms = searchTerms(this.query);
            const counts = { shown: 0, onCard: 0, text: 0, noYear: 0, noConference: 0, noSpeaker: 0, noConferenceYear: 0 };
            const byYear = new Map();
            const byYearIgnoringYear = new Map();
            const byConference = new Map();
            // Searching shows one ranked list instead of the year sections.
            const flat = this.flat;
            const top = [];
            const rest = [];
            const spoken = [];
            const fit = new Map();
            // Two or more words: talks with some of them are ranked by how rare those words are.
            const weights = flat && terms.length > 1 ? this.weigh(terms) : null;
            const some = [];
            // The same, with the Laracon filter dropped (for the chips) or the year dropped (for the ledger and year menu).
            const someByConference = new Map();
            const someByYear = new Map();
            let someAnyConference = 0;
            // Fewer than PARTIAL_BELOW talks have every word on the card or among their keywords: a talk
            // that says every word anywhere in it (from /said) is a full match too, listed with "Said only in
            // the talk". Decided on the whole archive, so a filter change never switches it on or off.
            const fewOnCards = terms.length > 0 && rows.filter((row) => matchesAll(row.all, terms)).length < PARTIAL_BELOW;
            heard = fewOnCards && this.keywordsState !== 'loading' ? this.heardFor(terms, splitParts(this.query)) : null;
            const heardScores = new Map();

            // With the keywords missing too, their notice already says what the search covered.
            this.saidMissing = fewOnCards && this.saidState === 'failed' && this.keywordsState !== 'failed';
            // Everything said in the talks was searched, which includes the keywords.
            this.saidCovered = heard !== null;

            for (const row of rows) {
                const onCard = matchesAll(row.all, terms);
                const text = onCard || (heard !== null && this.saysAll(row, terms, heard));
                const conferenceOk = this.conference === 'all' || row.conference === this.conference;
                const yearOk = this.year === '' || row.year === this.year;
                const speakerOk = this.speaker === '' || row.speakers.includes(this.speaker);
                const match = text && conferenceOk && yearOk && speakerOk;

                if (text) {
                    counts.text++;

                    if (speakerOk && conferenceOk) {
                        counts.noYear++;
                        byYearIgnoringYear.set(row.year, (byYearIgnoringYear.get(row.year) ?? 0) + 1);
                    }

                    if (speakerOk && yearOk) {
                        counts.noConference++;
                        byConference.set(row.conference, (byConference.get(row.conference) ?? 0) + 1);
                    }

                    if (conferenceOk && yearOk) {
                        counts.noSpeaker++;
                    }

                    if (speakerOk) {
                        counts.noConferenceYear++;
                    }
                }

                if (match) {
                    counts.shown++;
                    counts.onCard += onCard ? 1 : 0;
                    byYear.set(row.year, (byYear.get(row.year) ?? 0) + 1);

                    if (flat && matchesAll(row.headline, terms)) {
                        top.push(row);
                        fit.set(row, closeness(row, terms, normalize(this.query)));
                    } else if (flat && matchesAll(row.haystack, terms)) {
                        rest.push(row);
                    } else if (flat) {
                        spoken.push(row);

                        if (!onCard) {
                            heardScores.set(row, this.heardScore(row, terms, heard));
                        }
                    }
                }

                const partialMatch = weights && !text && speakerOk ? this.partialFit(row, terms, weights) : null;

                if (partialMatch) {
                    if (conferenceOk && yearOk) {
                        some.push({ row, ...partialMatch });
                    }

                    if (yearOk) {
                        someAnyConference++;
                        someByConference.set(row.conference, (someByConference.get(row.conference) ?? 0) + 1);
                    }

                    if (conferenceOk) {
                        someByYear.set(row.year, (someByYear.get(row.year) ?? 0) + 1);
                    }
                }

                if (row.item.hidden === match) {
                    row.item.hidden = !match;
                }
            }

            this.suggestion = counts.shown === 0 ? this.relaxation(counts, terms) : null;

            let corrections = counts.shown === 0 ? this.didYouMean(counts, terms) : counts.onCard === 0 && heard !== null ? this.respelling(terms, counts.shown) : [];
            // A correction that keeps every searched word answers the search: "livewrie testing" is
            // answered by "livewire testing", not by every talk about testing.
            const corrected = corrections.some(({ kind, text }) => kind !== 'fewer' && text.split(' ').length >= terms.length);

            // Only when full matches are scarce. The rarest words first, so a common word's titles can't
            // crowd out a rare word's talks; then where they match (title, summary, captions). A stable
            // sort, so equally good partial matches stay newest first.
            // Talks that only say the words somewhere (from /said) don't count here: they can be many, and a
            // talk with a rare searched word in its title is often the one meant ("select options checklists").
            const offerSome = weights !== null && counts.onCard < PARTIAL_BELOW && !corrected;
            const ordered = offerSome ? some.sort((a, b) => b.score - a.score || a.tier - b.tier) : [];
            const partial = ordered.slice(0, PARTIAL_LIMIT).map(({ row }) => row);

            for (const row of partial) {
                row.item.hidden = false;
            }

            // A correction of one word on its own drops the others ("little" for "isolation brittle tests");
            // with talks that have some of the words listed, offer the reader's own words instead.
            if (partial.length > 0 && !corrected && corrections.some(({ kind }) => kind === 'query')) {
                corrections = this.fewerWords(terms);
            }

            this.corrections = corrections;
            this.shown = counts.shown;
            this.partial = partial.length;
            this.partialTotal = ordered.length;
            // A stable sort, so talks that fit equally well stay newest first.
            // Talks whose keywords have every word, newest first, then talks that say them all elsewhere, most often first.
            const heardOrder = (a, b) => (heardScores.has(a) ? 1 : 0) - (heardScores.has(b) ? 1 : 0) || (heardScores.get(b) ?? 0) - (heardScores.get(a) ?? 0);

            this.rank(
                flat ? { top: top.sort((a, b) => fit.get(a) - fit.get(b)), rest, spoken: spoken.sort(heardOrder), some: partial, someTotal: ordered.length } : null,
                terms,
            );

            // Each chip, year and ledger column counts what choosing it would show: its full matches, or,
            // when it has none, its talks with some of the words, as the heading would then say.
            // Those counts of talks with some of the words are marked (`some`: a dashed count, and "with some
            // of the words" for screen readers), so a chip can't pass for having more full matches than All.
            const somePossible = weights !== null && !corrected;
            const orSome = (full, some) => (full > 0 || !somePossible || some === 0 ? { count: full, some: false } : { count: some, some: true });
            const yearCount = (year) => orSome(byYearIgnoringYear.get(year) ?? 0, someByYear.get(year) ?? 0);
            let anySome = false;

            // The ledger and the year menu ignore the year filter, so they keep showing where else the matches are.
            for (const { col, year, ticks, count, label } of tickCols) {
                const { count: lit, some } = yearCount(year);

                ticks.style.setProperty('--lit', lit);
                count.textContent = numbers.format(lit);
                col.toggleAttribute('data-some', some);
                label.textContent = this.t(some ? 'ledgerLabelSome' : 'ledgerLabel', { year, talks: this.plural('talks', lit) });
                anySome ||= some;
            }

            for (const [year, option] of yearOptions) {
                const { count, some } = yearCount(year);

                option.textContent = this.t(some ? 'yearOptionSome' : 'yearOption', { year, count: numbers.format(count) });
            }

            // Conference chips count what each would show with the other filters kept.
            for (const [slug, el] of facets) {
                const { count, some } =
                    slug === 'all' ? orSome(counts.noConference, someAnyConference) : orSome(byConference.get(slug) ?? 0, someByConference.get(slug) ?? 0);
                const chip = el.closest('.chip');

                el.textContent = numbers.format(count);
                chip.querySelector('[data-facet-note]').textContent = some ? ` ${this.strings.someWords}` : '';
                chip.toggleAttribute('data-empty', count === 0);
                chip.toggleAttribute('data-some', some);
                anySome ||= some;
            }

            // Explained under the heading only when full and partial counts are on show together; with no
            // full match, the heading already says the counts are of talks with some of the words.
            this.mixedCounts = anySome && counts.shown > 0;

            for (const group of groups) {
                const count = byYear.get(group.year) ?? 0;

                group.el.hidden = flat || count === 0;
                group.count.textContent = this.plural('talks', count);
            }

            // Not every Laracon ran, or was filmed, every year; say so rather than imply a search problem.
            this.eventMissing =
                counts.shown === 0 && this.conference !== 'all' && this.year !== '' && !rows.some((row) => row.conference === this.conference && row.year === this.year);

            this.highlight(terms);
        },

        /**
         * Search results as one list, best match first: talks with every word in the title,
         * speakers or event, then talks that match only in their summary, then talks that
         * match only in what is said in them, each newest first. The cards move into these
         * lists, so reading and Tab order follow the ranking. Browsing (`results` is null)
         * puts every moved card back in its year, in page order.
         */
        rank(results, terms) {
            for (const row of saying) {
                row.said.hidden = true;
            }

            saying.clear();

            if (results === null) {
                // Move only the cards that are out of place, so cards that never left stay put.
                for (const home of disturbed) {
                    let expected = home.firstElementChild;

                    for (const { item } of homes.get(home)) {
                        if (item === expected) {
                            expected = expected.nextElementSibling;
                        } else {
                            home.insertBefore(item, expected);
                        }
                    }
                }

                disturbed.clear();
                moved = new Set();
                ranked.el.hidden = true;
                ranked.someEl.hidden = true;

                return;
            }

            const { top, rest, spoken, some, someTotal } = results;
            const next = new Set([...top, ...rest, ...spoken, ...some]);
            const place = (list, items) => {
                const same = list.children.length === items.length && items.every((row, index) => list.children[index] === row.item);

                if (!same) {
                    list.replaceChildren(...items.map((row) => row.item));
                }

                list.hidden = items.length === 0;
            };

            // Cards that no longer match go back to their year (hidden) until browsing re-sorts it.
            for (const row of moved) {
                if (!next.has(row)) {
                    row.home.append(row.item);
                }
            }

            for (const row of next) {
                disturbed.add(row.home);
            }

            place(ranked.top, top);
            place(ranked.rest, rest);
            place(ranked.spoken, spoken);
            place(ranked.some, some);
            moved = next;

            for (const row of [...spoken, ...some]) {
                this.showSaid(row, terms);
            }

            ranked.el.hidden = next.size === some.length;
            ranked.someEl.hidden = some.length === 0;
            // "12 closest of 22" when there are more than the list shows.
            ranked.someCount.textContent =
                someTotal > some.length
                    ? this.t('closestOf', { shown: numbers.format(some.length), count: numbers.format(someTotal) })
                    : this.plural('talks', some.length);
            ranked.topHeading.hidden = top.length === 0;
            ranked.topHeading.textContent = this.query.trim() ? this.strings.rankedTop : this.strings.rankedAll;
            ranked.restHead.hidden = rest.length === 0;
            ranked.restCount.textContent = this.plural('talks', rest.length);
            ranked.spokenHead.hidden = spoken.length === 0;
            ranked.spokenCount.textContent = this.plural('talks', spoken.length);
        },

        /**
         * "Said in the talk: mental, illness, struggling": for each searched word the card itself
         * doesn't show, in the order typed, the word from the talk that matched it.
         */
        showSaid(row, terms) {
            const hits = [];
            const wanted = [...terms, ...(terms.length > 1 ? [terms.join('')] : [])].filter((term) => !termMatches(row.haystack, term));

            for (const term of wanted) {
                // From the keywords, or else the form of the word the talk says most often (see heardFor).
                const index = terms.indexOf(term);
                const fromHeard = index >= 0 && heard?.[index] instanceof Map ? heard[index].get(row.card.id)?.word : undefined;
                const word = row.words.find((candidate) => termMatches(` ${candidate} `, term)) ?? fromHeard;

                if (word && !hits.includes(word)) {
                    hits.push(word);
                }
            }

            if (hits.length === 0) {
                return;
            }

            if (!row.said) {
                row.said = document.createElement('p');
                row.said.className = 'card-said';
                row.card.querySelector('.card-speakers').after(row.said);
            }

            const words = document.createElement('span');

            // The captions are English, whatever the interface language.
            words.lang = 'en';
            words.append(
                ...hits.flatMap((word, index) => [
                    ...(index > 0 ? [document.createTextNode(this.strings.listSeparator)] : []),
                    Object.assign(document.createElement('mark'), { textContent: word }),
                ]),
            );
            row.said.replaceChildren(document.createTextNode(`${this.strings.saidInTalk} `), words);
            row.said.hidden = false;
            saying.add(row);
        },

        /**
         * How much each searched word counts towards a partial match: rarer words count more
         * (log of talks / talks with the word). A word no talk has counts for nothing. See
         * partialFit() for which talks are offered.
         */
        weigh(terms) {
            const weights = new Map();

            const inHeadlines = new Map();

            for (const term of terms) {
                const having = rows.reduce((count, row) => count + (termMatches(row.all, term) ? 1 : 0), 0);

                weights.set(term, having > 0 ? Math.log((rows.length + 1) / having) : 0);
                inHeadlines.set(term, rows.reduce((count, row) => count + (termMatches(row.headline, term) ? 1 : 0), 0));
            }

            weights.half = [...weights.values()].reduce((sum, weight) => sum + weight, 0) / 2;
            // Words few headlines have: a talk with one in its title is offered even without the others
            // ("mysql scout" lists the MySQL talks). They follow talks with rarer words.
            weights.rare = new Set(terms.filter((term) => inHeadlines.get(term) > 0 && inHeadlines.get(term) <= RARE_IN_HEADLINE));

            return weights;
        },

        /**
         * Whether a talk without every searched word is still worth offering: its words carry at
         * least half of the search's weight, or one of them is a rare word in its title, speakers or
         * event. `tier` is where its best word is: 0 headline, 1 summary, 2 only in what is said.
         */
        partialFit(row, terms, weights) {
            let score = 0;
            let tier = 2;
            let anchored = false;

            for (const term of terms) {
                if (!termMatches(row.all, term)) {
                    continue;
                }

                score += weights.get(term);

                if (termMatches(row.headline, term)) {
                    tier = 0;
                    anchored ||= weights.rare.has(term);
                } else if (termMatches(row.haystack, term)) {
                    tier = Math.min(tier, 1);
                }
            }

            return score > 0 && (score >= weights.half || anchored) ? { score, tier } : null;
        },

        /** The smallest change that turns zero results into some: drop the year, then the Laracon, then the speaker. */
        relaxation(counts, terms) {
            const query = this.query.trim();
            const options = [
                { when: this.year !== '', count: counts.noYear, key: 'relaxYear', state: { year: '' } },
                { when: this.conference !== 'all', count: counts.noConference, key: 'relaxConference', state: { conference: 'all' } },
                { when: this.speaker !== '', count: counts.noSpeaker, key: 'relaxSpeaker', state: { speaker: '' } },
                {
                    when: this.year !== '' && this.conference !== 'all',
                    count: counts.noConferenceYear,
                    key: 'relaxConferenceYear',
                    state: { conference: 'all', year: '' },
                },
                { when: terms.length > 0, count: counts.text, key: 'relaxAll', state: { conference: 'all', year: '', speaker: '' } },
            ];

            const option = options.find(({ when, count }) => when && count > 0);

            if (!option) {
                return null;
            }

            // When the offer would remove every filter, "Clear all filters" would do the same thing.
            const after = { ...this.snapshot(), ...option.state };
            const clearsAll = after.query.trim() === '' && after.conference === 'all' && after.year === '' && after.speaker === '';

            return { label: this.plural(option.key, option.count, { query }), state: option.state, clearsAll };
        },

        /** "Did you mean …": a misspelt speaker link first, then misspelt search words. */
        didYouMean(counts, terms) {
            if (this.speaker && !rows.some((row) => row.speakers.includes(this.speaker))) {
                const name = this.closestSpeaker(this.speaker);

                return name ? [{ kind: 'speaker', text: name, count: rows.filter((row) => row.speakers.includes(name)).length }] : [];
            }

            if (counts.text > 0 || terms.length === 0) {
                return [];
            }

            // Without the words said in the talks, a real word that only they have can look misspelt: "mockery"
            // is two edits from "docker". So only one-edit corrections then ("elequent" → "eloquent", "livewrie"
            // → "livewire"), and the notice under the heading offers Try again.
            const corrections = this.spellcheck(terms, this.keywordsState === 'failed' ? 1 : 2);

            // Also offered above talks with some of the words: one word on its own can find more ("mysql scout").
            return corrections.length > 0 || terms.length < 2 ? corrections : this.fewerWords(terms);
        },

        /**
         * Every match comes only from what is said in the talks, and automatic captions misspell words: a
         * respelling of the whole search that finds more talks on their cards is offered above them
         * ("elequent" finds one talk whose captions say it; "Did you mean “eloquent” (16)?").
         */
        respelling(terms, shown) {
            return this.spellcheck(terms, this.keywordsState === 'failed' ? 1 : 2)
                .filter(({ text, count }) => text.split(' ').length === terms.length && count > shown)
                .slice(0, 1);
        },

        /**
         * Every word is spelt right, but no talk has all of them ("mysql scout"): offer the
         * search without one word, or failing that, each word on its own, with their counts.
         */
        fewerWords(terms) {
            const matching = (words) => rows.filter((row) => words.every((word) => termMatches(row.all, word))).length;
            const offer = (sets) =>
                sets
                    .map((words) => ({ kind: 'fewer', text: words.join(' '), count: matching(words) }))
                    .filter(({ count }) => count > 0)
                    .slice(0, 3);
            const withoutOne = terms.length > 2 ? offer(terms.map((_, skip) => terms.filter((__, index) => index !== skip))) : [];

            // Not a one- or two-letter word on its own: "on" (211) says nothing about "copy-on-write clones".
            return withoutOne.length > 0 ? withoutOne : offer(terms.filter((term) => !isShort(term)).map((term) => [term]));
        },

        /** What goes before the offer at `index`: a comma, or "or" before the last one. */
        joiner(index, option) {
            if (index === 0) {
                return '';
            }

            if (index < this.corrections.length - 1) {
                return this.strings.listSeparator;
            }

            return /^h?o/i.test(option.text) ? this.strings.orBeforeO : this.strings.or;
        },

        /** The sentence around the offered words, split at the place they go. */
        get correctionLead() {
            const fewer = this.partial > 0 ? this.strings.fewerWordsPartial : this.strings.fewerWords;
            const template = this.corrections[0]?.kind === 'fewer' ? fewer : this.strings.didYouMean;
            const [before, after = ''] = template.split(':suggestion');

            // Every offer says how many talks it finds: "Did you mean “livewire testing” (2)?"
            return { before, after, counts: true };
        },

        closestSpeaker(name) {
            speakerNames ??= [...new Set(rows.flatMap((row) => row.speakers))];

            const wanted = normalize(name).trim();
            const max = Math.max(2, Math.floor(wanted.length / 4));
            let best = null;
            let bestCost = max + 1;

            for (const candidate of speakerNames) {
                const cost = distance(wanted, normalize(candidate).trim(), max);

                if (cost < bestCost) {
                    best = candidate;
                    bestCost = cost;
                }
            }

            return best;
        },

        applyCorrection(option) {
            if (option.kind === 'speaker') {
                this.showSpeaker(option.text);
            } else {
                this.searchFor(option.text, true);
            }
        },

        /** "Did you mean …" from the words in titles, speakers and conference names. */
        spellcheck(terms, maxEdits) {
            vocabulary ??= new Set(
                rows.flatMap((row) =>
                    normalize([row.card.querySelector('.card-title').textContent, ...row.speakers, row.card.dataset.conference].join(' '))
                        .trim()
                        .split(' ')
                        .filter((word) => word.length >= 3),
                ),
            );

            // Up to three close words for each term that matches nothing; a term that matches stays as typed.
            const options = terms.map((term) => {
                if (rows.some((row) => termMatches(row.all, term))) {
                    return [{ word: term, cost: 0 }];
                }

                const max = Math.min(maxEdits, term.length <= 4 ? 1 : 2);
                const close = [];

                for (const word of vocabulary) {
                    const cost = distance(term, word.slice(0, Math.max(term.length, Math.min(word.length, term.length + 2))), max);

                    if (cost <= max) {
                        close.push({ word, cost });
                    }
                }

                return close.sort((a, b) => a.cost - b.cost || a.word.length - b.word.length).slice(0, 3);
            });

            if (options.every((list) => list.length === 0 || list[0].cost === 0)) {
                return [];
            }

            const matching = (words) => rows.filter((row) => words.every((word) => termMatches(row.all, word))).length;

            // Every combination (at most 3 × 3 × …), closest first, until one finds talks.
            if (options.every((list) => list.length > 0)) {
                const combinations = options
                    .reduce((sets, list) => sets.flatMap((set) => list.map((option) => [...set, option])), [[]])
                    .sort((a, b) => a.reduce((sum, o) => sum + o.cost, 0) - b.reduce((sum, o) => sum + o.cost, 0));

                for (const set of combinations) {
                    const words = set.map(({ word }) => word);
                    const count = matching(words);

                    if (count > 0) {
                        return [{ kind: 'query', text: words.join(' '), count }];
                    }
                }
            }

            // No combination finds anything together: offer each corrected word on its own.
            return options
                .flatMap((list) => (list.length > 0 && list[0].cost > 0 ? [list[0].word] : []))
                .map((word) => ({ kind: 'query', text: word, count: matching([word]) }))
                .filter(({ count }) => count > 0)
                .slice(0, 3);
        },

        /** A one-line offer, in their language, for readers whose browser prefers another interface language. */
        findLanguageHint() {
            let dismissed = false;

            try {
                dismissed = localStorage.getItem('language-hint') === 'dismissed';
            } catch {
                // Storage disabled: the hint can still be closed for this visit.
            }

            const preferred = (navigator.languages?.[0] ?? navigator.language ?? '').toLowerCase().split('-')[0];

            if (dismissed || !preferred || preferred === locale || !languages[preferred]?.ask) {
                return null;
            }

            return { code: preferred, ...languages[preferred] };
        },

        dismissLanguageHint() {
            const hadFocus = Boolean(document.activeElement?.closest('.language-hint'));

            this.languageHint = null;

            // The offer is gone; continue from the start of the page rather than from nowhere.
            if (hadFocus) {
                this.$nextTick(() => root.querySelector('.brand').focus());
            }

            try {
                localStorage.setItem('language-hint', 'dismissed');
            } catch {
                // Nothing to remember it in; it stays closed until the next page load.
            }
        },

        /** Wrap matching words in <mark>, built from text nodes so nothing is parsed as HTML. */
        highlight(terms) {
            for (const { el, text } of marked) {
                el.textContent = text;
            }

            marked = [];

            if (terms.length === 0) {
                return;
            }

            // The words written together too, so "native php" marks NativePHP.
            const alternatives = [...terms, ...(terms.length > 1 ? [terms.join('')] : [])]
                .sort((a, b) => b.length - a.length)
                .flatMap((term) => {
                    const { words, stems } = formsOf(term);

                    return [
                        ...words.map((word) => (isShort(word) ? `${escapeRegExp(word)}(?![\\p{L}\\p{N}])` : escapeRegExp(word))),
                        // A stem only with one of its endings, and the whole word marked: "organization", not "organiz".
                        ...stems.map((stem) => `${escapeRegExp(stem)}${ENDINGS}(?![\\p{L}\\p{N}])`),
                    ];
                });
            const pattern = new RegExp(`(?<![\\p{L}\\p{N}])((?:${alternatives.join('|')}))`, 'giu');

            for (const row of rows) {
                if (row.item.hidden) {
                    continue;
                }

                for (const mark of row.marks) {
                    const pieces = mark.text.split(pattern);

                    if (pieces.length === 1) {
                        continue;
                    }

                    mark.el.replaceChildren(
                        ...pieces.map((piece, index) => {
                            if (index % 2 === 0) {
                                return document.createTextNode(piece);
                            }

                            const highlight = document.createElement('mark');
                            highlight.textContent = piece;

                            return highlight;
                        }),
                    );

                    marked.push(mark);
                }
            }
        },

        /** Polite screen-reader update once typing settles, not on every keystroke. */
        announce() {
            clearTimeout(announceTimer);
            announceTimer = setTimeout(() => {
                const notice = this.keywordsMissing ? this.strings.keywordsFailed : this.saidMissing ? this.strings.saidFailed : null;

                this.status = notice ? `${this.summary}. ${notice}` : this.summary;
            }, 650);
        },

        revealResults() {
            const top = this.resultsTop();

            if (Math.abs(window.scrollY - top) > 1) {
                window.scrollTo({ top, behavior: scrollBehavior() });
            }

            focusQuietly(document.getElementById('results-title'));
        },

        revealResultsIfHidden() {
            const heading = document.getElementById('results-title').getBoundingClientRect();

            if (heading.top > window.innerHeight * 0.8) {
                window.scrollTo({ top: this.resultsTop(), behavior: scrollBehavior() });
            }
        },

        resultsTop() {
            return refs.sentinel.getBoundingClientRect().top + window.scrollY;
        },

        /** Bring a #permalink into view below the pinned bars, clearing filters that would hide it. */
        revealHash() {
            const id = decodeURIComponent(location.hash.slice(1));
            const target = id ? document.getElementById(id) : null;

            // A talk or year from the part of the archive that is still loading: show where it
            // will appear and say it is loading, then go there, unless the reader moves on first.
            if (!target && id && loading) {
                const placeholder = root.querySelector('[data-earlier]');
                const inputs = ['wheel', 'touchstart', 'keydown'];
                let movedOn = false;
                const moveOn = () => (movedOn = true);

                placeholder?.scrollIntoView({ block: 'center', behavior: 'instant' });
                this.status = this.strings.loadingEarlier;
                inputs.forEach((type) => window.addEventListener(type, moveOn, { passive: true }));

                loading.then(() => {
                    inputs.forEach((type) => window.removeEventListener(type, moveOn));

                    // Take "Loading…" out of the live region: landing on the year or talk announces
                    // itself, and a reader who moved on hears what the page now shows. (A failed
                    // load has already said so.)
                    if (this.earlierState === 'loaded') {
                        this.status = movedOn ? this.summary : '';
                    }

                    if (!movedOn) {
                        this.revealHash();
                    }
                });

                return;
            }

            // The earlier years could not be loaded: the full page has every talk and year.
            const earlierYear = /^(?:talks-)?(\d{4})(?:-|$)/.exec(id)?.[1];

            if (!target && this.earlierState === 'failed' && this.years.includes(earlierYear)) {
                location.assign(`${location.pathname}?all${location.hash}`);

                return;
            }

            if (!target) {
                return;
            }

            // "Timeline" should land on the chart, not on its folded toggle.
            if (id === 'timeline') {
                this.ledgerOpen = true;
            }

            const row = rows.find((candidate) => candidate.card === target);

            if (target.closest('.groups [hidden]')) {
                Object.assign(this, { query: '', conference: 'all', year: '', speaker: '' });
                // Show the talk now, so the scroll below measures the final layout.
                this.apply();
            }

            // Focus follows the jump, so Tab continues from there: the talk itself, or the
            // heading of a year or section (a focused region is read out as "clickable").
            const focusTarget = row || target.matches('h1, h2, h3') ? target : (target.querySelector('h2, h3') ?? target);
            const scroll = () => {
                target.scrollIntoView({ block: 'start', behavior: 'instant' });
                focusQuietly(focusTarget);
            };

            // Cards near the target render at their real height a frame or two after the
            // jump, and web fonts can arrive later still, so for about a second put the
            // talk back below the pinned bars whenever it drifts. Any scroll input from
            // the reader stops this at once.
            const stops = ['wheel', 'touchstart', 'keydown', 'pointerdown'];
            let frames = 0;
            let stopped = false;
            const stop = () => {
                stopped = true;
                stops.forEach((type) => window.removeEventListener(type, stop));
            };
            const keep = () => {
                if (stopped) {
                    return;
                }

                const margin = parseFloat(getComputedStyle(target).scrollMarginTop) || 0;

                if (Math.abs(target.getBoundingClientRect().top - margin) > 2) {
                    target.scrollIntoView({ block: 'start', behavior: 'instant' });
                }

                if (++frames < 60) {
                    requestAnimationFrame(keep);
                } else {
                    stop();
                }
            };

            requestAnimationFrame(() =>
                requestAnimationFrame(() => {
                    scroll();
                    stops.forEach((type) => window.addEventListener(type, stop, { passive: true }));
                    requestAnimationFrame(keep);
                }),
            );
        },

        /* ---------------------------------------------------------------
           URL state — every filtered view can be shared and Back undoes it.
        --------------------------------------------------------------- */

        snapshot() {
            return { query: this.query, conference: this.conference, year: this.year, speaker: this.speaker };
        },

        readUrl() {
            const params = new URLSearchParams(location.search);
            const conference = params.get('conference');
            const year = params.get('year');

            return {
                query: params.get('q') ?? '',
                conference: conference && conference in this.conferences ? conference : 'all',
                year: year && this.years.includes(year) ? year : '',
                speaker: params.get('speaker') ?? '',
            };
        },

        writeUrl(mode, { keepHash = false } = {}) {
            if (!initialised) {
                return;
            }

            const search = this.searchString;
            const url = `${location.pathname}${search ? `?${search}` : ''}${keepHash ? location.hash : this.keptHash()}`;

            if (url === `${location.pathname}${location.search}${location.hash}`) {
                return;
            }

            history[mode === 'push' ? 'pushState' : 'replaceState'](null, '', url);
        },

        /**
         * A #permalink stays in the address bar only while its talk is still shown.
         * Otherwise reopening the URL would clear the filters to reveal it.
         */
        keptHash() {
            const id = decodeURIComponent(location.hash.slice(1));
            const target = id ? document.getElementById(id) : null;

            return target?.closest('.groups [hidden]') ? '' : location.hash;
        },

        /* ---------------------------------------------------------------
           Layout observers
        --------------------------------------------------------------- */

        watchLayout() {
            const root = document.documentElement;
            const yearHead = root.querySelector('.year-head');
            const bars = new ResizeObserver(() => {
                root.style.setProperty('--toolbar-h', `${refs.toolbar.offsetHeight}px`);

                // Search views hide the year headings (1 px); keep the last real height for when they return.
                if (yearHead && yearHead.offsetHeight > 1) {
                    root.style.setProperty('--yearhead-h', `${yearHead.offsetHeight}px`);
                }
            });

            bars.observe(refs.toolbar);

            if (yearHead) {
                bars.observe(yearHead);
            }

            // The bar is pinned once the page has scrolled to its resting place, including
            // when "/" or the header Search button scrolls there exactly.
            const update = () => {
                const top = this.resultsTop();

                measured = { top, scrollY: window.scrollY };
                this.stuck = window.scrollY > 0 && window.scrollY >= top - 1;
                this.farDown = window.scrollY > top + window.innerHeight * 1.5;
            };

            // Resize observations arrive after layout, when measuring is free.
            new ResizeObserver(update).observe(document.documentElement);

            let ticking = false;

            window.addEventListener(
                'scroll',
                () => {
                    if (ticking) {
                        return;
                    }

                    ticking = true;

                    requestAnimationFrame(() => {
                        ticking = false;
                        update();
                    });
                },
                { passive: true },
            );

            update();
        },

        /** Offer "Show more" only on summaries that are actually cut off at their current width. */
        watchSummaries() {
            summaryObserver = new ResizeObserver((entries) => {
                for (const { target } of entries) {
                    if (!target.classList.contains('is-open') && target.clientHeight > 0) {
                        target.nextElementSibling.hidden = target.scrollHeight <= target.clientHeight + 1;
                    }
                }
            });

            root.querySelectorAll('[data-summary]').forEach((summary) => summaryObserver.observe(summary));
        },

        /* ---------------------------------------------------------------
           Copy
        --------------------------------------------------------------- */

        t(key, replacements = {}) {
            return (this.strings[key] ?? key).replace(/:(\w+)/g, (match, name) => (name in replacements ? replacements[name] : match));
        },

        plural(key, count, replacements = {}) {
            const forms = this.strings[key];

            return this.t(forms[plurals.select(count)] ?? forms.other, { count: numbers.format(count), ...replacements });
        },
    };
});

/**
 * Theme: system, light or dark. The inline script in <head> paints the right
 * one before first render; this keeps it in sync with the switch and the OS.
 */
Alpine.data('theme', (labels) => {
    const media = matchMedia('(prefers-color-scheme: dark)');

    const saved = () => {
        try {
            return localStorage.getItem('theme');
        } catch {
            return null;
        }
    };

    return {
        choice: ['light', 'dark'].includes(saved()) ? saved() : 'system',

        get current() {
            return labels.current.replace(':theme', labels[this.choice]);
        },

        /** The one-button version on narrow phones steps System → Light → Dark. */
        cycle() {
            this.choose({ system: 'light', light: 'dark', dark: 'system' }[this.choice]);
        },

        init() {
            media.addEventListener('change', () => this.paint());
        },

        choose(choice) {
            this.choice = choice;

            try {
                if (choice === 'system') {
                    localStorage.removeItem('theme');
                } else {
                    localStorage.setItem('theme', choice);
                }
            } catch {
                // Private mode or storage disabled: the choice still applies for this visit.
            }

            this.paint();
        },

        paint() {
            const dark = this.choice === 'dark' || (this.choice === 'system' && media.matches);

            document.documentElement.classList.toggle('dark', dark);
        },
    };
});

Alpine.start();
