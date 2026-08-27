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
    <meta property="og:image" content="{{ url('/og.png') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="The Laracon Archive — fourteen years of Laracon, kept in one record.">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="The Laracon Archive — {{ $total }} talks, 2013–2026">
    <meta name="twitter:description" content="Every recorded Laracon talk from 2013 to 2026 across Laracon US, EU, Online, AU and India.">
    <meta name="twitter:image" content="{{ url('/og.png') }}">

    <script>
        // Set the theme before first paint so the page never flashes.
        (() => {
            const saved = localStorage.getItem('theme');
            const dark = saved ? saved === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.classList.toggle('dark', dark);
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist+Mono:wght@400;500&family=Instrument+Sans:wght@400;500;600&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
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

    <main class="shell">
        <section class="hero">
            <div>
                <p class="eyebrow">A record of the Laravel community, in talks</p>

                <h1>Fourteen years of Laracon, kept in <em>one record</em>.</h1>

                <p class="hero-lede">
                    Since the inaugural Laracon in Washington, DC in 2013, this community has documented
                    itself out loud — on stage, on video, once or twice a year, on five continents' worth of
                    stages. Here are all <strong>{{ $total }}</strong> recorded talks, from Laracon US, EU,
                    Online, AU and India. Search it the way you'd search a codebase.
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
                <div class="ledger-head">
                    <p class="eyebrow">One mark per talk</p>
                    <p class="eyebrow">Pick a year to filter</p>
                </div>

                <div class="ledger-rows">
                    @foreach ($years as $y)
                        <button type="button"
                                class="ledger-row"
                                data-tick-year="{{ $y['year'] }}"
                                @click="toggleYear('{{ $y['year'] }}')"
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
        </section>
    </main>

    <div class="controls">
        <div class="shell">
            <div class="controls-inner">
                <div class="search-row">
                    <div class="search-field">
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                            <circle cx="7" cy="7" r="4.5"/>
                            <path d="M10.5 10.5 14 14" stroke-linecap="round"/>
                        </svg>
                        <label for="talk-search" class="sr-only">Search the Laracon archive by year, conference, title, speaker or summary</label>
                        <input id="talk-search" type="search" x-ref="search" x-model="query"
                               autocomplete="off" spellcheck="false"
                               placeholder="Search {{ $total }} talks — try “queues”, “Eloquent”, “Otwell”">
                    </div>
                    <kbd class="search-hint">/</kbd>
                    <p class="result-count" role="status" aria-live="polite">
                        <b x-text="shown">{{ $total }}</b><span class="count-of"> of {{ $total }}</span>
                        <span class="sr-only">talks shown</span>
                    </p>
                </div>

                <div class="chips">
                    <button type="button" class="chip" @click="conference = 'all'"
                            :aria-pressed="conference === 'all' ? 'true' : 'false'" aria-pressed="true">
                        All <span>{{ $total }}</span>
                    </button>
                    @foreach ($conferences as $conference)
                        <button type="button" class="chip" @click="setConference('{{ $conference['slug'] }}')"
                                :aria-pressed="conference === '{{ $conference['slug'] }}' ? 'true' : 'false'"
                                aria-pressed="false">
                            <span class="sr-only">Laracon </span>{{ $conference['short'] }} <span>{{ $conference['count'] }}</span>
                        </button>
                    @endforeach

                    <button type="button" class="chip-clear" x-show="filtered" x-cloak @click="clear()">
                        Clear filters
                        <span x-show="year !== null" x-cloak>· <span x-text="year"></span></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="shell">
        <table class="archive-table" x-show="shown > 0">
            <caption class="sr-only">All {{ $total }} recorded Laracon talks, newest year first.</caption>
            <thead>
                <tr>
                    <th scope="col" class="cell-year">Year</th>
                    <th scope="col" class="cell-conf">Conference</th>
                    <th scope="col" class="cell-title">Talk</th>
                    <th scope="col" class="cell-speaker">Speaker</th>
                    <th scope="col" class="cell-summary">Summary</th>
                    <th scope="col" class="cell-watch"><span class="sr-only">Video</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($talks as $talk)
                    <tr data-conference="{{ $talk['slug'] }}"
                        data-year="{{ $talk['year'] }}">
                        <td class="cell-year mono">{{ $talk['year'] }}</td>
                        <td class="cell-conf">
                            <span class="conf-tag"><span class="sr-only">Laracon </span>{{ $talk['short'] }}</span>
                        </td>
                        <td class="cell-title">{{ $talk['title'] }}</td>
                        <td class="cell-speaker">{{ $talk['speaker'] }}</td>
                        <td class="cell-summary">{{ $talk['summary'] }}</td>
                        <td class="cell-watch">
                            <a class="watch" href="{{ $talk['youtube_url'] }}" target="_blank" rel="noopener noreferrer"
                               aria-label="Watch “{{ $talk['title'] }}” on YouTube (opens in a new tab)">
                                Watch
                                <svg viewBox="0 0 10 10" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                                    <path d="M2.5 7.5 7.5 2.5M3.6 2.5h3.9v3.9" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="empty" x-show="shown === 0" x-cloak>
            <h2>No talks match that.</h2>
            <p>
                The archive covers {{ $years->count() }} years and {{ $conferences->count() }} conferences, so
                try a broader term — a speaker's surname, a package name, or a single year.
            </p>
            <button type="button" @click="clear()">Clear filters</button>
        </div>
    </div>

    <footer class="shell">
        <div class="site-footer">
            <p>
                {{ $total }} talks compiled from public Laracon recordings, {{ $years->first()['year'] }} back to
                {{ $years->last()['year'] }}. Videos are hosted on YouTube by their respective conferences.
            </p>
            <p>
                An independent archive. Not affiliated with Laravel or any Laracon organiser.
            </p>
        </div>
    </footer>
</div>
</body>
</html>
