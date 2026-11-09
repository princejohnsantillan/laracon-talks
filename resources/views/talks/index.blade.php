@php
    $firstYear = $years->last()['year'];
    $lastYear = $years->first()['year'];
    $repository = 'https://github.com/princejohnsantillan/laracon-talks';
    $locale = str_replace('_', '-', app()->getLocale());
    $direction = in_array(Str::before($locale, '-'), ['ar', 'fa', 'he', 'ur'], true) ? 'rtl' : 'ltr';
    // Spelt out to agree with "years" in each language: «veintiún años», not «veintiuno años».
    $spellout = new NumberFormatter(app()->getLocale(), NumberFormatter::SPELLOUT);
    $spellout->setTextAttribute(NumberFormatter::DEFAULT_RULESET, ['es' => '%spellout-cardinal-masculine'][app()->getLocale()] ?? '%spellout-cardinal');
    $yearsSpelled = Str::ucfirst($spellout->format($years->count()));
    $latestEvents = $talksByYear->first()->pluck('conference')->unique()->values();
    $chronological = $years->sortBy('year')->values();

    $pageTitle = __('The Laracon Archive — :total talks, :from–:to', ['total' => $total, 'from' => $firstYear, 'to' => $lastYear]);
    $pageDescription = __('Every recorded Laracon talk from :from to :to: :total talks across Laracon US, EU, Online, AU and India. Search by speaker, title, year or topic.', ['from' => $firstYear, 'to' => $lastYear, 'total' => $total]);

    // Whole sentences, so a translation can reorder every part. Keys name the active
    // filters: q(uery), s(peaker), c(onference), y(ear). "zero" is the empty-results heading, and
    // "some" heads talks with some of the searched words when none has them all.
    $summaries = [
        'q' => ['some' => ['one' => __('No full matches for “:query”, but :count talk has some of the words'), 'other' => __('No full matches for “:query”, but :count talks have some of the words')], 'zero' => __('No talks matching “:query”'), 'one' => __(':count talk matching “:query”'), 'other' => __(':count talks matching “:query”')],
        's' => ['zero' => __('No talks by :speaker'), 'one' => __(':count talk by :speaker'), 'other' => __(':count talks by :speaker')],
        'c' => ['zero' => __('No talks at :conference'), 'one' => __(':count talk at :conference'), 'other' => __(':count talks at :conference')],
        'y' => ['zero' => __('No talks in :year'), 'one' => __(':count talk in :year'), 'other' => __(':count talks in :year')],
        'qs' => ['some' => ['one' => __('No full matches for “:query” by :speaker, but :count talk has some of the words'), 'other' => __('No full matches for “:query” by :speaker, but :count talks have some of the words')], 'zero' => __('No talks matching “:query” by :speaker'), 'one' => __(':count talk matching “:query” by :speaker'), 'other' => __(':count talks matching “:query” by :speaker')],
        'qc' => ['some' => ['one' => __('No full matches for “:query” at :conference, but :count talk has some of the words'), 'other' => __('No full matches for “:query” at :conference, but :count talks have some of the words')], 'zero' => __('No talks matching “:query” at :conference'), 'one' => __(':count talk matching “:query” at :conference'), 'other' => __(':count talks matching “:query” at :conference')],
        'qy' => ['some' => ['one' => __('No full matches for “:query” in :year, but :count talk has some of the words'), 'other' => __('No full matches for “:query” in :year, but :count talks have some of the words')], 'zero' => __('No talks matching “:query” in :year'), 'one' => __(':count talk matching “:query” in :year'), 'other' => __(':count talks matching “:query” in :year')],
        'sc' => ['zero' => __('No talks by :speaker at :conference'), 'one' => __(':count talk by :speaker at :conference'), 'other' => __(':count talks by :speaker at :conference')],
        'sy' => ['zero' => __('No talks by :speaker in :year'), 'one' => __(':count talk by :speaker in :year'), 'other' => __(':count talks by :speaker in :year')],
        'cy' => ['zero' => __('No talks at :conference :year'), 'one' => __(':count talk at :conference :year'), 'other' => __(':count talks at :conference :year')],
        'qsc' => ['some' => ['one' => __('No full matches for “:query” by :speaker at :conference, but :count talk has some of the words'), 'other' => __('No full matches for “:query” by :speaker at :conference, but :count talks have some of the words')], 'zero' => __('No talks matching “:query” by :speaker at :conference'), 'one' => __(':count talk matching “:query” by :speaker at :conference'), 'other' => __(':count talks matching “:query” by :speaker at :conference')],
        'qsy' => ['some' => ['one' => __('No full matches for “:query” by :speaker in :year, but :count talk has some of the words'), 'other' => __('No full matches for “:query” by :speaker in :year, but :count talks have some of the words')], 'zero' => __('No talks matching “:query” by :speaker in :year'), 'one' => __(':count talk matching “:query” by :speaker in :year'), 'other' => __(':count talks matching “:query” by :speaker in :year')],
        'qcy' => ['some' => ['one' => __('No full matches for “:query” at :conference :year, but :count talk has some of the words'), 'other' => __('No full matches for “:query” at :conference :year, but :count talks have some of the words')], 'zero' => __('No talks matching “:query” at :conference :year'), 'one' => __(':count talk matching “:query” at :conference :year'), 'other' => __(':count talks matching “:query” at :conference :year')],
        'scy' => ['zero' => __('No talks by :speaker at :conference :year'), 'one' => __(':count talk by :speaker at :conference :year'), 'other' => __(':count talks by :speaker at :conference :year')],
        'qscy' => ['some' => ['one' => __('No full matches for “:query” by :speaker at :conference :year, but :count talk has some of the words'), 'other' => __('No full matches for “:query” by :speaker at :conference :year, but :count talks have some of the words')], 'zero' => __('No talks matching “:query” by :speaker at :conference :year'), 'one' => __(':count talk matching “:query” by :speaker at :conference :year'), 'other' => __(':count talks matching “:query” by :speaker at :conference :year')],
    ];

    $strings = [
        'talks' => ['one' => __(':count talk'), 'other' => __(':count talks')],
        'summaries' => $summaries,
        'showingAll' => __('Showing all :count talks'),
        'yearOption' => __(':year (:count)'),
        'ledgerLabel' => __(':year: :talks'),
        // A count of talks with some of the searched words, shown with a ~ (see app.js apply()).
        'ledgerLabelSome' => __(':year: :talks with some of the words'),
        'yearOptionSome' => __(':year (~:count)'),
        'someWords' => __('with some of the words'),
        'relaxYear' => ['one' => __('Show :count talk from every year'), 'other' => __('Show :count talks from every year')],
        'relaxConference' => ['one' => __('Show :count talk from every Laracon'), 'other' => __('Show :count talks from every Laracon')],
        'relaxSpeaker' => ['one' => __('Show :count talk by any speaker'), 'other' => __('Show :count talks by any speaker')],
        'relaxConferenceYear' => ['one' => __('Show :count talk from every Laracon and year'), 'other' => __('Show :count talks from every Laracon and year')],
        'relaxAll' => ['one' => __('Show the :count talk matching “:query”'), 'other' => __('Show all :count talks matching “:query”')],
        'quoted' => __('“:text”'),
        'searchingAll' => __('Searching all :count talks…'),
        'rankedTop' => __('Matches in the title, speaker or event'),
        'rankedAll' => __('Talks'),
        'saidInTalk' => __('Said in the talk:'),
        'loadingEarlier' => __('Loading the earlier talks…'),
        'earlierFailed' => __('The earlier talks didn’t load.'),
        'didYouMean' => __('Did you mean :suggestion?'),
        'fewerWords' => __('No talk has all of these words. Try :suggestion.'),
        'fewerWordsPartial' => __('Search fewer words: :suggestion.'),
        'closestOf' => __(':shown closest of :count'),
        'keywordsFailed' => __('The words said in the talks didn’t load, so this search covers titles, speakers, events and summaries only.'),
        'saidFailed' => __('Everything else said in the talks couldn’t be searched, so talks that only mention these words in passing may be missing.'),
        'eventMissing' => __('The archive has no recordings from :conference :year. Not every Laracon was held, or filmed, every year.'),
        'or' => __(' or '),
        // Spanish writes «u» for «o» before a word that starts with an «o» sound: «octave» u «octane».
        'orBeforeO' => ['es' => ' u '][app()->getLocale()] ?? __(' or '),
        'listSeparator' => __(', '),
        'clearSearch' => __('Clear search'),
        'clearFilters' => __('Clear all filters'),
        'missingTalk' => __('Missing talk: :query'),
        'copied' => __('Link to this talk copied'),
        'copyFailed' => __('Couldn’t copy. The link is now in your address bar.'),
        'cleared' => __('Filters cleared'),
        'searchCleared' => __('Search cleared'),
        'more' => __('Show more'),
        'less' => __('Show less'),
    ];

    $themeLabels = ['system' => __('System theme'), 'light' => __('Light theme'), 'dark' => __('Dark theme'), 'current' => __('Color theme: :theme')];
    // The Spanish copy is neutral, not Spain's: es_LA is Meta's umbrella locale for Spanish.
    $og = ['en' => 'en_US', 'es' => 'es_LA'][app()->getLocale()] ?? 'en_US';
    $otherLanguages = collect($languages)->except(app()->getLocale());
    // Offered to readers whose browser prefers another interface language, in that language.
    $languageHints = [
        'en' => ['ask' => 'Prefer English?', 'go' => 'View this archive in English', 'dismiss' => 'No thanks'],
        'es' => ['ask' => '¿Prefieres leer en español?', 'go' => 'Ver el archivo en español', 'dismiss' => 'No, gracias'],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $direction }}" class="antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#fdfdfc" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#010618" media="(prefers-color-scheme: dark)">
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $pageDescription }}">

    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

    @foreach ($languages as $code => $language)
        <link rel="alternate" hreflang="{{ $code }}" href="{{ $language['absolute'] }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ $languages['en']['absolute'] }}">
    <link rel="canonical" href="{{ $languages[app()->getLocale()]['absolute'] }}">

    <meta property="og:type" content="website">
    <meta property="og:locale" content="{{ $og }}">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $pageDescription }}">
    <meta property="og:image" content="{{ asset('og.png') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="{{ __('The Laracon Archive — fourteen years of Laracon, kept in one record.') }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $pageTitle }}">
    <meta name="twitter:description" content="{{ $pageDescription }}">
    <meta name="twitter:image" content="{{ asset('og.png') }}">

    <script>
        // Set the theme before first paint so the page never flashes.
        (() => {
            const root = document.documentElement;
            let saved = null;

            try {
                saved = localStorage.getItem('theme');
            } catch {}

            const dark = saved === 'dark' || (saved !== 'light' && matchMedia('(prefers-color-scheme: dark)').matches);

            root.classList.add('js');
            root.classList.toggle('dark', dark);
        })();
    </script>

    {{-- Fonts are served from this site: no third-party requests for type. --}}
    <link rel="preload" href="{{ asset('fonts/instrument-sans-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/hanken-grotesk-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preconnect" href="https://i.ytimg.com">

    @if ($earlier)
        {{-- The years after the first two, fetched while the page renders; app.js picks up this response. --}}
        <link rel="preload" href="{{ $earlier['url'] }}" as="fetch" crossorigin fetchpriority="low">
    @endif
    {{-- The words each talk says, for search; app.js picks up this response too. --}}
    <link rel="preload" href="{{ $keywordsUrl }}" as="fetch" crossorigin fetchpriority="low">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<svg width="0" height="0" class="sprite" aria-hidden="true" focusable="false">
    <symbol id="i-x" viewBox="0 0 20 20"><path d="m6 6 8 8M14 6l-8 8"/></symbol>
    <symbol id="i-system" viewBox="0 0 20 20"><rect x="2.5" y="3.5" width="15" height="10" rx="1.5"/><path d="M7 16.5h6M10 13.5v3"/></symbol>
    <symbol id="i-light" viewBox="0 0 20 20"><circle cx="10" cy="10" r="3.5"/><path d="M10 1.5v2M10 16.5v2M1.5 10h2M16.5 10h2M4 4l1.4 1.4M14.6 14.6 16 16M4 16l1.4-1.4M14.6 5.4 16 4"/></symbol>
    <symbol id="i-globe" viewBox="0 0 20 20"><circle cx="10" cy="10" r="7.5"/><path d="M2.5 10h15M10 2.5c2.2 2.2 3.2 4.7 3.2 7.5s-1 5.3-3.2 7.5c-2.2-2.2-3.2-4.7-3.2-7.5s1-5.3 3.2-7.5Z"/></symbol>
    <symbol id="i-dark" viewBox="0 0 20 20"><path d="M16.5 12.2A7 7 0 0 1 7.8 3.5a7 7 0 1 0 8.7 8.7Z"/></symbol>
</svg>

<div x-data="archive(@js(['total' => $total, 'strings' => $strings, 'repository' => $repository, 'conferences' => $conferences->pluck('label', 'slug'), 'years' => $years->pluck('year')->map(fn ($year) => (string) $year), 'languages' => collect($languages)->map(fn ($language, $code) => ['url' => $language['url'], 'name' => $language['name']] + ($languageHints[$code] ?? [])), 'locale' => app()->getLocale(), 'earlier' => $earlier ? $earlier['url'] : null, 'keywords' => $keywordsUrl, 'said' => $saidUrl]))"
     :class="{ 'is-pending': pending }"
     @keydown.window="onKeydown($event)">

    <template x-if="languageHint">
        <aside class="language-hint" :lang="languageHint.code" aria-labelledby="language-hint-text">
            <div class="shell language-hint-inner">
                <p id="language-hint-text" x-text="languageHint.ask"></p>
                <a :href="withFilters(languageHint.url)" :hreflang="languageHint.code" x-text="languageHint.go"></a>
                <button type="button" class="text-button" @click="dismissLanguageHint()" x-text="languageHint.dismiss"></button>
            </div>
        </aside>
    </template>

    <a class="skip-link" href="#talk-search">{{ __('Skip to search') }}</a>
    <a class="skip-link" href="#results-title">{{ __('Skip to results') }}</a>

    <header class="site-header">
        <div class="shell header-inner">
            <a href="{{ $languages[app()->getLocale()]['url'] }}" class="brand" aria-label="{{ __('The Laracon Archive, home') }}">
                <span class="brand-mark" aria-hidden="true"><i></i><i></i><i></i></span>
                <span class="brand-name">{{ __('Laracon') }} <span>{{ __('Archive') }}</span></span>
            </a>

            <nav class="primary-nav" aria-label="{{ __('Sections') }}">
                <a href="#results-title">{{ __('Talks') }}</a>
                <a href="#timeline" @click="ledgerOpen = true">{{ __('Timeline') }}</a>
                <a href="#about">{{ __('About') }}</a>
            </nav>

            <div class="header-actions">
                @foreach ($otherLanguages as $code => $language)
                    <a class="lang-link" href="{{ $language['url'] }}" :href="withFilters(@js($language['url']))" hreflang="{{ $code }}" lang="{{ $code }}" title="{{ $language['name'] }}">
                        <svg viewBox="0 0 20 20" aria-hidden="true"><use href="#i-globe"/></svg>
                        <span class="lang-link-name">{{ $language['name'] }}</span>
                        <span class="lang-link-code" aria-hidden="true">{{ Str::upper($code) }}</span>
                    </a>
                @endforeach

                <button type="button" class="search-trigger" @click="jumpToSearch()">
                    <svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="9" cy="9" r="5.5"/><path d="m13.2 13.2 3.8 3.8"/></svg>
                    <span class="search-trigger-label">{{ __('Search') }}</span>
                    <kbd aria-hidden="true">/</kbd>
                </button>

                <div class="theme" x-data="theme(@js($themeLabels))">
                    <div class="theme-switch" role="group" aria-label="{{ __('Color theme') }}">
                        <button type="button" @click="choose('system')" :aria-pressed="choice === 'system'" aria-pressed="true" title="{{ __('Match my system') }}">
                            <svg viewBox="0 0 20 20" aria-hidden="true"><use href="#i-system"/></svg>
                            <span class="sr-only">{{ __('System theme') }}</span>
                        </button>
                        <button type="button" @click="choose('light')" :aria-pressed="choice === 'light'" aria-pressed="false" title="{{ __('Light theme') }}">
                            <svg viewBox="0 0 20 20" aria-hidden="true"><use href="#i-light"/></svg>
                            <span class="sr-only">{{ __('Light theme') }}</span>
                        </button>
                        <button type="button" @click="choose('dark')" :aria-pressed="choice === 'dark'" aria-pressed="false" title="{{ __('Dark theme') }}">
                            <svg viewBox="0 0 20 20" aria-hidden="true"><use href="#i-dark"/></svg>
                            <span class="sr-only">{{ __('Dark theme') }}</span>
                        </button>
                    </div>
                    {{-- Narrow phones: one button that steps System → Light → Dark. --}}
                    <button type="button" class="icon-button theme-cycle" @click="cycle()" :title="current" title="{{ __('Color theme: :theme', ['theme' => __('System theme')]) }}">
                        <svg viewBox="0 0 20 20" aria-hidden="true"><use :href="`#i-${choice}`" href="#i-system"/></svg>
                        <span class="sr-only" x-text="current">{{ __('Color theme: :theme', ['theme' => __('System theme')]) }}</span>
                    </button>
                </div>

                <a class="icon-button header-github" href="{{ $repository }}" rel="noopener" target="_blank" title="{{ __('Source on GitHub') }}">
                    <svg viewBox="0 0 16 16" aria-hidden="true" class="fill"><path d="M8 0a8 8 0 0 0-2.53 15.59c.4.07.55-.17.55-.38v-1.33c-2.23.48-2.7-1.07-2.7-1.07-.36-.92-.89-1.17-.89-1.17-.73-.5.06-.49.06-.49.8.06 1.23.83 1.23.83.71 1.22 1.87.87 2.33.66.07-.52.28-.87.5-1.07-1.78-.2-3.65-.89-3.65-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82a7.6 7.6 0 0 1 4 0c1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.28.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48v2.2c0 .21.15.46.55.38A8 8 0 0 0 8 0Z"/></svg>
                    <span class="sr-only">{{ __('Source on GitHub (opens in a new tab)') }}</span>
                </a>

                {{-- Below 768 px the section links and the language switch live in this menu. --}}
                <button type="button" class="icon-button menu-button" popovertarget="site-menu" aria-label="{{ __('Menu') }}" title="{{ __('Menu') }}">
                    <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M3.5 6h13M3.5 10h13M3.5 14h13"/></svg>
                </button>
            </div>
        </div>

        <nav id="site-menu" class="site-menu" popover aria-label="{{ __('Menu') }}" @click="$event.target.closest('a') && $el.hidePopover?.()">
            <a href="#results-title">{{ __('Talks') }}</a>
            <a href="#timeline" @click="ledgerOpen = true">{{ __('Timeline') }}</a>
            <a href="#about">{{ __('About') }}</a>
            @foreach ($otherLanguages as $code => $language)
                <a href="{{ $language['url'] }}" :href="withFilters(@js($language['url']))" hreflang="{{ $code }}" lang="{{ $code }}" class="site-menu-language">
                    <svg viewBox="0 0 20 20" aria-hidden="true"><use href="#i-globe"/></svg>{{ $language['name'] }}
                </a>
            @endforeach
        </nav>
    </header>

    <main id="main">
        <section class="shell hero" aria-labelledby="hero-title">
            <div class="hero-panel navy">
                <div class="hero-copy">
                    <h1 id="hero-title">{{ __(':years years of Laracon.', ['years' => $yearsSpelled]) }} <em>{{ __('Every recorded talk, in one place.') }}</em></h1>

                    <p class="hero-lede">
                        {{ __(':total recorded talks by :speakers speakers, :from to :to. Find them by topic, speaker or year, then watch on YouTube.', ['total' => Number::format($total), 'speakers' => Number::format($speakerCount), 'from' => $firstYear, 'to' => $lastYear]) }}
                    </p>

                    <div class="topics">
                        <p class="topics-label" id="topics-label">{{ __('Popular topics') }}</p>
                        <ul class="topics-list" aria-labelledby="topics-label">
                            @foreach ($topics as $topic)
                                <li>
                                    <button type="button" class="topic" @click="searchFor(@js($topic))" :aria-pressed="isTopic(@js($topic))" aria-pressed="false">{{ $topic }}</button>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                {{-- The ledger: one mark per talk, oldest year on the left. Lit marks match the current filters. --}}
                <div class="ledger" id="timeline" :class="{ 'is-open': ledgerOpen }">
                    <div class="ledger-head">
                        <h2 id="ledger-title">
                            <span class="ledger-title-text">{{ __('Talks per year') }}</span>
                            <button type="button" class="ledger-toggle" @click="ledgerOpen = !ledgerOpen" :aria-expanded="ledgerOpen ? 'true' : 'false'" aria-expanded="false" aria-controls="ledger-cols">
                                {{ __('Talks per year') }}
                                <svg viewBox="0 0 20 20" aria-hidden="true"><path d="m6 8 4 4 4-4"/></svg>
                            </button>
                        </h2>
                        <p id="ledger-hint">{{ __('One mark per talk. Choose a year to filter.') }}</p>
                    </div>

                    <div class="ledger-cols" id="ledger-cols" role="toolbar" aria-labelledby="ledger-title" aria-describedby="ledger-hint" @keydown="onLedgerKeydown($event)">
                        @foreach ($chronological as $y)
                            <button type="button"
                                    class="ledger-col"
                                    data-tick-year="{{ $y['year'] }}"
                                    tabindex="{{ $loop->last ? 0 : -1 }}"
                                    @click="toggleYear('{{ $y['year'] }}', $event)"
                                    :aria-pressed="year === '{{ $y['year'] }}' ? 'true' : 'false'"
                                    aria-pressed="false">
                                <span class="ledger-count" aria-hidden="true" data-tick-count>{{ $y['count'] }}</span>
                                <span class="ledger-ticks" aria-hidden="true" style="--n: {{ $y['count'] }}; --lit: {{ $y['count'] }}"></span>
                                <span class="ledger-year" aria-hidden="true"><span class="ledger-year-full">{{ $y['year'] }}</span><span class="ledger-year-short">{{ __('’:yy', ['yy' => substr((string) $y['year'], 2)]) }}</span></span>
                                <span class="sr-only" data-tick-label>{{ __(':year: :talks', ['year' => $y['year'], 'talks' => trans_choice(':count talk|:count talks', $y['count'])]) }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        <div class="toolbar-sentinel" x-ref="sentinel" aria-hidden="true"></div>

        <div class="toolbar" :class="{ 'is-stuck': stuck }" x-ref="toolbar">
            <div class="shell">
                <div class="toolbar-row">
                    <form class="search" role="search" @submit.prevent="$refs.search.blur()">
                        <label for="talk-search" class="sr-only">{{ __('Search talks by title, speaker or topic') }}</label>
                        <svg class="search-icon" viewBox="0 0 20 20" aria-hidden="true"><circle cx="9" cy="9" r="5.5"/><path d="m13.2 13.2 3.8 3.8"/></svg>
                        <input id="talk-search" type="search" x-ref="search" x-model.debounce.120ms="query"
                               @keydown.escape="onSearchEscape($event)" @input="onSearchInput()"
                               autocomplete="off" spellcheck="false" enterkeyhint="search" aria-describedby="search-hint"
                               placeholder="{{ __('Search :total talks by title, speaker or topic', ['total' => $total]) }}"
                               :placeholder="narrowest ? @js(__('Search')) : compact ? @js(__('Title, speaker or topic')) : @js(__('Search :total talks by title, speaker or topic', ['total' => $total]))">
                        <button type="button" class="search-clear" x-show="query !== ''" x-cloak @click="clearQuery()">
                            <svg viewBox="0 0 20 20" aria-hidden="true"><use href="#i-x"/></svg>
                            <span class="sr-only">{{ __('Clear search') }}</span>
                        </button>
                        <kbd class="search-key" x-show="query === ''" title="{{ __('Press / to search') }}" aria-hidden="true">/</kbd>
                    </form>

                    <div class="select">
                        <label for="year-filter" class="sr-only">{{ __('Year') }}</label>
                        <select id="year-filter" x-model="year">
                            {{-- Phones label the unset menu "Year", leaving the search field room for its placeholder. --}}
                            <option value="" x-text="compact ? @js(__('Year')) : @js(__('All years'))">{{ __('All years') }}</option>
                            @foreach ($years as $y)
                                <option value="{{ $y['year'] }}" data-year-option="{{ $y['year'] }}">{{ __(':year (:count)', ['year' => $y['year'], 'count' => $y['count']]) }}</option>
                            @endforeach
                        </select>
                        <svg viewBox="0 0 20 20" aria-hidden="true"><path d="m6 8 4 4 4-4"/></svg>
                    </div>
                </div>

                <div class="filter-row">
                    <div class="chips-scroll">
                        <span class="filter-label" id="conference-label">{{ __('Laracon') }}</span>
                        <div class="chips" role="group" aria-labelledby="conference-label">
                            <button type="button" class="chip" @click="setConference('all')"
                                    :aria-pressed="conference === 'all' ? 'true' : 'false'" aria-pressed="true">
                                {{ __('All') }} <span class="chip-count" data-facet="all">{{ $total }}</span><span class="sr-only" data-facet-note></span>
                            </button>
                            @foreach ($conferences as $conference)
                                <button type="button" class="chip" @click="setConference('{{ $conference['slug'] }}')"
                                        :aria-pressed="conference === '{{ $conference['slug'] }}' ? 'true' : 'false'"
                                        aria-pressed="false">
                                    <i class="tone tone-{{ $conference['slug'] }}" aria-hidden="true"></i>
                                    <span class="sr-only">{{ __('Laracon') }} </span>{{ $conference['short'] }}
                                    <span class="chip-count" data-facet="{{ $conference['slug'] }}">{{ $conference['count'] }}</span><span class="sr-only" data-facet-note></span>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div class="active-filters" x-show="filtered || undo" x-cloak>
                        <button type="button" class="chip chip-removable" x-show="speaker" @click="speaker = ''">
                            <span class="chip-key">{{ __('Speaker') }}</span> <span x-text="speaker"></span>
                            <svg viewBox="0 0 20 20" aria-hidden="true"><use href="#i-x"/></svg>
                            <span class="sr-only">{{ __('— remove speaker filter') }}</span>
                        </button>
                        <button type="button" class="text-button" x-show="filtered" @click="clearAll()">{{ __('Clear all') }}</button>
                        <p class="undo" x-show="undo && !filtered">
                            <span id="undo-message" x-text="undo?.message"></span>
                            <button type="button" class="text-button" x-ref="undoButton" aria-describedby="undo-message" @click="runUndo()">{{ __('Undo') }}</button>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <section id="talks" class="shell results" aria-labelledby="results-title">
            <div class="results-head">
                <h2 id="results-title">
                    <span x-text="summary">{{ __('Showing all :count talks', ['count' => $total]) }}</span>
                </h2>
                <p class="results-notice" x-show="keywordsMissing" x-cloak>
                    <span x-text="strings.keywordsFailed"></span>
                    <button type="button" class="text-button" @click="retryKeywords()">{{ __('Try again') }}</button>
                </p>
                <p class="results-notice" x-show="saidMissing && !pending" x-cloak>
                    <span x-text="strings.saidFailed"></span>
                    <button type="button" class="text-button" @click="retrySaid()">{{ __('Try again') }}</button>
                </p>
                {{-- Talks matched only by what is said in them, when a respelling finds more on the cards ("elequent"). --}}
                <p class="did-you-mean results-offer" x-show="shown > 0 && corrections.length > 0 && !pending" x-cloak>@include('talks.partials.corrections')</p>
                <p class="results-note" x-show="mixedCounts && !pending" x-cloak>{{ __('Counts marked ~ are talks with some of the words, which is what choosing them shows.') }}</p>
                <p class="results-hint" id="search-hint" x-show="!filtered">{{ __('Words match from their start, so “live” finds Livewire; one- and two-letter terms like “ai” match whole words. Select a speaker to see all of their talks.') }}</p>
            </div>
            <nav class="year-index" x-show="!filtered" aria-label="{{ __('Jump to a year') }}">
                <span class="year-index-label" aria-hidden="true">{{ __('Jump to') }}</span>
                @foreach ($years as $y)
                    <a href="#talks-{{ $y['year'] }}">{{ $y['year'] }}</a>
                @endforeach
            </nav>
            <p class="sr-only" role="status" aria-live="polite" x-text="status"></p>
            <p id="speaker-hint" hidden>{{ __('Shows all of their talks') }}</p>

            {{-- Everything below is stale while a filter waits (pending): dimmed, busy and out of reach until it lands. --}}
            <div class="results-body" :aria-busy="pending ? 'true' : 'false'" :inert="pending">
                <div class="groups" :class="{ 'is-flat': flat }">
                    {{--
                        Search results, best match first. The page moves the matching cards here: talks
                        with every word in the title, speakers or event, then talks that match only in
                        their summary, then talks that match only in what is said in them (their captions).
                        Browsing puts them back in their years.
                    --}}
                    <div class="ranked" data-ranked hidden x-ignore>
                        <h3 class="sr-only" data-ranked-heading="top"></h3>
                        <ul class="cards" role="list" data-ranked-list="top"></ul>
                        <header class="ranked-divider" data-ranked-divider="rest">
                            <h3>{{ __('Matches only in the summary') }}</h3>
                            <p data-ranked-count="rest"></p>
                        </header>
                        <ul class="cards" role="list" data-ranked-list="rest"></ul>
                        <header class="ranked-divider" data-ranked-divider="spoken">
                            <h3>{{ __('Said only in the talk') }}</h3>
                            <p data-ranked-count="spoken"></p>
                            <p class="ranked-note">{{ __('Found in the talk’s captions, which can misspell names.') }}</p>
                        </header>
                        <ul class="cards" role="list" data-ranked-list="spoken"></ul>
                    </div>
                    @include('talks.partials.groups', ['talksByYear' => $renderedYears])
                </div>

                @if ($earlier)
                    {{-- Replaced by the earlier years as soon as they load. Without JavaScript, or if they fail to load, it links to the full page. --}}
                    <div class="groups-more" data-earlier :aria-busy="earlierState === 'loading' ? 'true' : 'false'" x-show="!flat && !pending">
                        <p x-show="earlierState === 'loading'" x-cloak>{{ trans_choice('Loading the :count talk from :year and earlier…|Loading the :count talks from :year and earlier…', $earlier['count'], ['count' => $earlier['count'], 'year' => $earlier['from']]) }}</p>
                        <p x-show="earlierState === 'failed'" x-cloak>{{ __('The earlier talks didn’t load.') }} <button type="button" class="text-button" @click="retryEarlier()">{{ __('Try again') }}</button></p>
                        <a href="?all#talks-{{ $earlier['from'] }}" x-show="earlierState !== 'loading'">{{ trans_choice('Show the :count talk from :year and earlier|Show the :count talks from :year and earlier', $earlier['count'], ['count' => $earlier['count'], 'year' => $earlier['from']]) }}</a>
                    </div>
                @endif

                {{-- With talks that have some of the words below, only a correction or a wider search shows here, inline. --}}
                <div class="empty" :class="{ 'is-compact': partial > 0 }" x-show="shown === 0 && !pending && (partial === 0 || corrections.length > 0 || suggestion)" x-cloak>
                    <svg class="empty-art" viewBox="0 0 120 64" aria-hidden="true" x-show="partial === 0">
                        @for ($i = 0; $i < 24; $i++)<rect x="{{ 4 + $i * 5 }}" y="{{ 18 + ($i % 5) * 3 }}" width="2" height="{{ 28 - ($i % 5) * 3 }}" rx="1"/>@endfor
                    </svg>
                    <p x-show="eventMissing" x-text="t('eventMissing', { conference: conferences[conference], year })"></p>
                    <p class="did-you-mean" x-show="corrections.length > 0">@include('talks.partials.corrections')</p>

                    <template x-if="suggestion">
                        <div class="empty-actions">
                            <button type="button" class="button button-primary" @click="applySuggestion()" x-text="suggestion.label"></button>
                            <button type="button" class="button" x-show="!suggestion.clearsAll" @click="clearAll()" x-text="clearLabel">{{ __('Clear all filters') }}</button>
                        </div>
                    </template>

                    <template x-if="!suggestion">
                        <div class="empty-body" x-show="partial === 0">
                            <p x-show="corrections.length === 0 && partial === 0">{{ __('Try a shorter word, a speaker’s surname or a package name.') }}</p>
                            <div class="empty-actions" x-show="partial === 0">
                                <button type="button" class="button" @click="clearAll()" x-text="clearLabel">{{ __('Clear all filters') }}</button>
                            </div>
                            <p class="empty-topics-label" id="empty-topics-label" x-show="partial === 0">{{ __('Or try a popular topic') }}</p>
                            <ul class="topics-list" aria-labelledby="empty-topics-label" x-show="partial === 0">
                                @foreach ($topics as $topic)
                                    <li><button type="button" class="topic topic-light" @click="searchFor(@js($topic), true)">{{ $topic }}</button></li>
                                @endforeach
                            </ul>
                            <p class="empty-report" x-show="partial === 0">
                                {{ __('Think a talk is missing?') }}
                                <a :href="reportUrl" href="{{ $repository }}/issues/new" rel="noopener" target="_blank">{{ __('Tell us on GitHub') }}<span class="sr-only"> {{ __('(opens in a new tab)') }}</span></a>
                            </p>
                        </div>
                    </template>
                </div>

                {{--
                    For searches of two or more words with fewer than three full matches: up to twelve
                    talks that have some of the words, the rarest words first, then those with them in the
                    title. They follow the full matches, or the heading when there are none.
                --}}
                <div class="ranked ranked-some" data-ranked-some hidden x-ignore>
                    <header class="ranked-divider">
                        <h3>{{ __('Talks with some of these words') }}</h3>
                        <p data-ranked-count="some"></p>
                    </header>
                    <ul class="cards" role="list" data-ranked-list="some"></ul>
                </div>

                {{-- After talks with some of the words: recovery comes once the alternatives have been seen. --}}
                <div class="partial-foot" x-show="shown === 0 && partial > 0 && !pending" x-cloak>
                    <button type="button" class="button" @click="clearAll()" x-text="clearLabel">{{ __('Clear search') }}</button>
                    <p class="empty-report">
                        {{ __('Think a talk is missing?') }}
                        <a :href="reportUrl" href="{{ $repository }}/issues/new" rel="noopener" target="_blank">{{ __('Tell us on GitHub') }}<span class="sr-only"> {{ __('(opens in a new tab)') }}</span></a>
                    </p>
                </div>
            </div>
        </section>

        <section id="about" class="shell about" aria-labelledby="about-title">
            <h2 id="about-title">{{ __('About the archive') }}</h2>
            <div class="about-grid">
                <div>
                    <h3>{{ __('What’s here') }}</h3>
                    <p>{{ __('Every recorded talk from Laracon US, EU, Online, AU and India since :year — :total in all, newest first. The latest are from :latest. Talks from conferences that were streamed as one long video open at the moment they start.', ['year' => $firstYear, 'total' => $total, 'latest' => $latestEvents->map(fn ($event) => "{$event} {$lastYear}")->join(', ', ' '.__('and').' ')]) }}</p>
                </div>
                <div>
                    <h3>{{ __('Where the data comes from') }}</h3>
                    <p>{{ __('Compiled by hand from public Laracon recordings. The full catalog is free to reuse as') }} <a href="{{ asset('talks.json') }}">talks.json</a>.</p>
                </div>
                <div>
                    <h3>{{ __('Spotted a mistake?') }}</h3>
                    <p>{{ __('A wrong speaker, a dead link or a missing talk — ') }}<a href="{{ $repository }}/issues/new" rel="noopener" target="_blank">{{ __('open an issue on GitHub') }}<span class="sr-only"> {{ __('(opens in a new tab)') }}</span></a>.</p>
                </div>
                <div>
                    <h3>{{ __('Privacy') }}</h3>
                    <p>{{ __('No cookies, accounts or analytics, and fonts are served from this site. Your theme choice stays in this browser. When few talks match a search, its words are sent to this site to look through everything said in the talks, and aren’t stored. Video thumbnails load from YouTube’s image servers, and videos play on YouTube.') }}</p>
                </div>
            </div>
        </section>
    </main>

    <footer class="shell site-footer">
        <p>{{ __('An independent archive. Not affiliated with Laravel, Laracasts or any Laracon organiser.') }}</p>
        <nav class="footer-links" aria-label="{{ __('Footer') }}">
            <a href="#results-title">{{ __('Talks') }}</a>
            <a href="#timeline" @click="ledgerOpen = true">{{ __('Timeline') }}</a>
            <a href="#about">{{ __('About') }}</a>
            <a href="{{ $repository }}" rel="noopener" target="_blank">{{ __('Source on GitHub') }}</a>
            <span>{{ __('Made with love by') }} <a href="https://x.com/pjsantillan" rel="me noopener" target="_blank">Prince John</a></span>
        </nav>
        <nav class="footer-links languages" aria-label="{{ __('Language') }}">
            @foreach ($languages as $code => $language)
                <a href="{{ $language['url'] }}" :href="withFilters(@js($language['url']))" hreflang="{{ $code }}" lang="{{ $code }}" @if ($code === app()->getLocale()) aria-current="page" @endif>{{ $language['name'] }}</a>
            @endforeach
        </nav>
    </footer>

    <button type="button" class="to-top" x-show="farDown" x-cloak x-transition.opacity @click="jumpToSearch()" title="{{ __('Back to the top of the results') }}">
        <svg viewBox="0 0 20 20" aria-hidden="true"><path d="m5 12 5-5 5 5"/></svg>
        <span class="sr-only">{{ __('Back to the top of the results') }}</span>
    </button>

    <div class="toast-region" role="status" aria-live="polite">
        <template x-if="toast">
            <div class="toast" x-text="toast"></div>
        </template>
    </div>
</div>
</body>
</html>
