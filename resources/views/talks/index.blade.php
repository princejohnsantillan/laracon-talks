<!DOCTYPE html>
<html lang="en" class="antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>The Laracon Archive — {{ $total }} talks, 2013–2026</title>
    <meta name="description" content="Every recorded Laracon talk from 2013 to 2026: {{ $total }} talks across Laracon US, EU, Online, AU and India. Search by speaker, title, year or topic.">

    <meta property="og:type" content="website">
    <meta property="og:title" content="The Laracon Archive — {{ $total }} talks, 2013–2026">
    <meta property="og:description" content="Every recorded Laracon talk from 2013 to 2026: {{ $total }} talks across Laracon US, EU, Online, AU and India.">
    <meta property="og:image" content="{{ asset('og.png') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="The Laracon Archive — fourteen years of Laracon, kept in one record.">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="The Laracon Archive — {{ $total }} talks, 2013–2026">
    <meta name="twitter:description" content="Every recorded Laracon talk from 2013 to 2026 across Laracon US, EU, Online, AU and India.">
    <meta name="twitter:image" content="{{ asset('og.png') }}">

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

    <script>
        // Set the theme before first paint so the page never flashes.
        (() => {
            let saved;
            try { saved = localStorage.getItem('theme'); } catch {}
            const dark = saved ? saved === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.classList.toggle('dark', dark);
        })();
    </script>

    <link rel="preload" href="{{ asset('fonts/instrument-sans-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/geist-mono-latin.woff2') }}" as="font" type="font/woff2" crossorigin>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<a class="skip-link" href="#talk-search">Skip to search and talks</a>
<div x-data="archive({{ $total }})" @keydown.window.slash="focusSearch($event)">

    <header class="shell">
        <div class="masthead">
            <div class="wordmark">
                <span class="wordmark-mark" aria-hidden="true"><i></i><i></i><i></i></span>
                The Laracon Archive
            </div>

            <div class="flex items-center gap-4">
                <span class="eyebrow hidden sm:block">2013 — 2026</span>
                <button type="button" class="theme-toggle" x-data="theme" @click="toggle()"
                        :aria-label="dark ? 'Switch to light theme' : 'Switch to dark theme'">
                    <span x-text="dark ? 'Light' : 'Dark'">Dark</span>
                </button>
            </div>
        </div>
    </header>

    <div class="rule-brand" role="presentation"></div>

    <main id="main-content">
        <section class="shell hero">
            <div>
                <p class="eyebrow">A record of the Laravel community, in talks</p>

                <h1>Fourteen years of Laracon, kept in <em>one record</em>.</h1>

                <p class="hero-lede">
                    <span class="hero-history">Since the inaugural Laracon in Washington, DC in 2013, this community has documented
                    itself out loud — on stage and on video. </span>Here are all <strong>{{ $total }}</strong> recorded talks, from Laracon US, EU,
                    Online, AU and India. Try a surname, Livewire, or a year.
                    Word beginnings work from 3 letters (2 for speakers).
                </p>

                <p class="hero-facts">
                    <span><b>{{ $total }}</b> talks</span>
                    <span><b>{{ $speakerCount }}</b> speakers</span>
                    <span><b>{{ $conferences->count() }}</b> Laracons</span>
                    <span><b>{{ $years->count() }}</b> years</span>
                </p>
            </div>

            {{-- The ledger: one tick mark per talk. Lit marks match the current search. --}}
            <div class="ledger">
                <button type="button" class="ledger-toggle" @click="ledgerOpen = !ledgerOpen"
                        :aria-expanded="ledgerOpen" aria-expanded="false" aria-controls="year-options">
                    <span class="disclosure-label">Browse by year <span aria-hidden="true" x-text="ledgerOpen ? '−' : '+'">+</span></span>
                    <span x-text="year ?? '2013–2026'"></span>
                </button>
                <div id="year-options" class="ledger-content mobile-collapsed" :class="{ 'mobile-collapsed': !ledgerOpen }">
                <div class="ledger-head">
                    <p class="eyebrow">One mark per talk</p>
                    <p class="eyebrow">Pick a year to filter</p>
                </div>
                <p class="ledger-help">Matching search and conference, before choosing a year.</p>
                <p id="year-keys" class="sr-only">Use Up and Down arrows to move between years, Home and End for the first and last. Press Enter to select.</p>

                <div class="ledger-rows" role="toolbar" aria-label="Filter by year" aria-orientation="vertical" aria-describedby="year-keys"
                     @keydown.up.prevent="focusYear($event)" @keydown.down.prevent="focusYear($event)"
                     @keydown.home.prevent="focusYear($event)" @keydown.end.prevent="focusYear($event)">
                    @foreach ($years as $y)
                        <button type="button"
                                class="ledger-row"
                                data-tick-year="{{ $y['year'] }}"
                                @click="toggleYear('{{ $y['year'] }}')"
                                @focus="yearFocus = '{{ $y['year'] }}'"
                                :tabindex="yearFocus === '{{ $y['year'] }}' ? 0 : -1" tabindex="{{ $loop->first ? 0 : -1 }}"
                                :aria-pressed="year === '{{ $y['year'] }}' ? 'true' : 'false'"
                                aria-pressed="false"
                                aria-label="Show only {{ $y['year'] }} — {{ $y['count'] }} talks">
                            <span class="ledger-year" aria-hidden="true">{{ $y['year'] }}</span>
                            <span class="ledger-ticks" aria-hidden="true">
                                @for ($i = 0; $i < $y['count']; $i++)<i class="lit"></i>@endfor
                            </span>
                            <span class="ledger-count" aria-hidden="true">{{ $y['count'] }}</span>
                        </button>
                    @endforeach
                </div>
                </div>
            </div>
        </section>

    <search class="controls" aria-label="Find talks" tabindex="-1">
        <div class="shell">
            <div class="controls-inner">
                <div class="search-row">
                    <div class="search-field">
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                            <circle cx="7" cy="7" r="4.5"/>
                            <path d="M10.5 10.5 14 14" stroke-linecap="round"/>
                        </svg>
                        <label for="talk-search" class="sr-only">Search the Laracon archive by year, conference, title, speaker or summary</label>
                        <input id="talk-search" type="search" x-ref="search" x-model.debounce.100ms="query"
                               autocomplete="off" spellcheck="false"
                               @focus="searchHistory = false" @keydown.enter.prevent="searchHistory = false"
                               aria-keyshortcuts="/" @keydown.escape.prevent="searchHistory = false; query = ''; $el.value = ''"
                               placeholder="Search {{ $total }} talks">
                        <button type="button" class="search-clear" x-show="query !== ''" x-cloak
                                @click="searchHistory = false; query = ''; $refs.search.focus()" aria-label="Clear search">×</button>
                    </div>
                    <kbd class="search-hint">/</kbd>
                    <p class="result-count">
                        <b x-text="shown">{{ $total }}</b><span class="count-of"> of {{ $total }} talks</span>
                        <span class="count-unit" x-text="shown === 1 ? ' talk' : ' talks'"> talks</span>
                    </p>
                    <p class="sr-only" role="status" aria-live="polite" x-text="announced + ' of ' + total + ' talks'">{{ $total }} of {{ $total }} talks</p>
                </div>

                <div class="filter-row">
                    <div class="chip-strip" :class="{ 'has-before': chipsBefore, 'has-after': chipsAfter }">
                        <div class="chips" role="group" aria-label="Conference filters" @focusin="if ($event.target.matches(':focus-visible')) revealChip($event.target.closest('.chip'))" @scroll.passive="measureChips()">
                            <button type="button" class="chip" @click="conference = 'all'"
                                    :aria-pressed="conference === 'all' ? 'true' : 'false'" aria-pressed="true">
                                All <span x-text="conferenceCounts.all ?? {{ $total }}">{{ $total }}</span>
                            </button>
                            @foreach ($conferences as $conference)
                                <button type="button" class="chip" @click="setConference('{{ $conference['slug'] }}')"
                                        :aria-pressed="conference === '{{ $conference['slug'] }}' ? 'true' : 'false'"
                                        :disabled="(conferenceCounts['{{ $conference['slug'] }}'] ?? 0) === 0 && conference !== '{{ $conference['slug'] }}'"
                                        :title="(conferenceCounts['{{ $conference['slug'] }}'] ?? 0) === 0 ? 'No matches for the current search and year' : 'Filter by Laracon {{ $conference['short'] }}'"
                                        aria-pressed="false">
                                    <span class="sr-only">Laracon </span>{{ $conference['short'] }} <span x-text="conferenceCounts['{{ $conference['slug'] }}'] ?? 0">{{ $conference['count'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                    <div class="active-filters" x-show="filtered" x-cloak>
                        <button type="button" class="chip-year" x-show="year !== null" @click="year = null"
                                :aria-label="'Remove year filter ' + year"><span x-text="year"></span> ×</button>
                        <button type="button" class="chip-clear" x-show="filtered" x-cloak @click="clear()" aria-label="Reset all filters">
                            <span class="clear-long">Reset all filters</span><span class="clear-short">Reset</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </search>

    <div class="shell" id="talks">
        <noscript><p class="no-script">Search and filters need JavaScript. All {{ $total }} talks are listed below; you can use your browser’s Find in page.</p></noscript>
        <table class="archive-table" x-show="shown > 0" role="table">
            <caption class="sr-only">All {{ $total }} recorded Laracon talks, newest year first.</caption>
            <thead role="rowgroup">
                <tr>
                    <th scope="col" class="cell-year">Year</th>
                    <th scope="col" class="cell-conf">Conference</th>
                    <th scope="col" class="cell-title">Talk</th>
                    <th scope="col" class="cell-speaker">Speaker</th>
                    <th scope="col" class="cell-summary">Summary</th>
                </tr>
            </thead>
            @foreach ($talks->groupBy('year') as $year => $yearTalks)
            <tbody role="rowgroup" @focusin="revealTalk($event)"
                :class="{ 'row-hidden': (year !== null && year !== '{{ $year }}') || !yearCounts['{{ $year }}'] }">
                <tr class="year-group">
                    <th scope="rowgroup" colspan="5">
                        {{ $year }}
                        <span class="year-total" x-text="yearCounts['{{ $year }}'] + (yearCounts['{{ $year }}'] === 1 ? ' talk' : ' talks')">{{ $yearTalks->count() }} {{ $yearTalks->count() === 1 ? 'talk' : 'talks' }}</span>
                    </th>
                </tr>
                @foreach ($yearTalks as $talk)
                    <tr role="row" data-conference="{{ $talk['slug'] }}"
                        data-year="{{ $talk['year'] }}">
                        <td class="cell-year mono">{{ $talk['year'] }}</td>
                        <td class="cell-conf">
                            <span class="conf-tag"><span class="sr-only">Laracon </span>{{ $talk['short'] }}</span>
                        </td>
                        <td class="cell-title"><a class="talk-link" href="{{ $talk['youtube_url'] }}" target="_blank" rel="noopener noreferrer"
                            aria-label="Watch “{{ $talk['title'] }}” on YouTube (opens in a new tab)">
                            <span class="talk-thumbnail" aria-hidden="true">
                                @if ($talk['thumbnail'])
                                    <img src="{{ $talk['thumbnail'] }}" alt="" width="320" height="180"
                                         loading="lazy" decoding="async" x-on:error="$el.remove()">
                                @endif
                            </span>
                            <span class="talk-copy">
                                <span class="watch" aria-hidden="true">
                                    Watch
                                    <svg viewBox="0 0 10 10" fill="none" stroke="currentColor" stroke-width="1.4">
                                        <path d="M2.5 7.5 7.5 2.5M3.6 2.5h3.9v3.9" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </span>
                                <span class="talk-title">{{ $talk['title'] }}</span>
                            </span>
                        </a></td>
                        <td class="cell-speaker">{{ $talk['speaker'] }}</td>
                        <td class="cell-summary">{{ $talk['summary'] }}</td>
                    </tr>
                @endforeach
            </tbody>
            @endforeach
        </table>

        <div class="empty" x-show="shown === 0 && settled" x-cloak>
            <h2 x-text="query !== '' ? 'No talks match “' + query + '”.' : 'No talks match these filters.'">No talks match these filters.</h2>
            <p x-show="query !== ''">Try 3 or more letters of a topic, 2 of a speaker’s name, or a complete short word such as AI.</p>
            <p x-show="query === ''">This conference and year have no recorded talks together. Choose another year or conference.</p>
            <button type="button" @click="clear()">Reset all filters</button>
        </div>
    </div>
    </main>

    <footer class="shell">
        <div class="site-footer">
            <p>
                {{ $total }} talks compiled from public Laracon recordings, {{ $years->first()['year'] }} back to
                {{ $years->last()['year'] }}. Videos are hosted on YouTube by their respective conferences.
            </p>
            <p>
                An independent archive. Not affiliated with Laravel or any Laracon organiser.
            </p>
            <p>
                Posters are served locally from public YouTube thumbnails. Talks from shared recordings may show the same event artwork.
            </p>
            <p>
                No analytics. Your theme stays in this browser; searches appear in shareable URLs.
                Opening a video takes you to YouTube.
            </p>
            <p>
                Made with love by <a href="https://x.com/pjsantillan" rel="me noopener" target="_blank">Prince John</a>
            </p>
        </div>
    </footer>
</div>
</body>
</html>
