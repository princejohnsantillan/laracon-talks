# The Laracon Archive

A single-page Laravel site that holds every recorded Laracon talk from 2013 to 2026 — **626 talks**
across Laracon US, Laracon EU, Laracon Online, Laracon AU and Laracon India — in one searchable,
filterable grid of talk cards.

The catalog is server-rendered. An unfiltered visit gets the two newest years in the first response
(42 KB gzipped), and the page appends the rest from `GET /earlier` (or `/earlier/es`) right after it
loads (the `<head>` preloads it, and the app gzips it), one year at a time. A filter applied before the
rest arrives shows "Searching all 626 talks…" until it can be answered; if `/earlier` fails, the page
offers Try again and sends filters and older-year jumps to the full page. A link with filters, or `?all`,
renders all 626 talks at once, and without JavaScript the page links to `?all`. Alpine.js filters what is on the page: instant search across year, conference, title, speaker and summary
(matching the start of words, with matches highlighted), conference chips, a year filter, and a
speaker view — click any speaker's name to see all of their talks. Search results come best match
first: talks with every word in the title, speakers or event (an exact title first, then the words in
the order typed), then, under their own heading, talks that match only in their summary; each group
is newest first. Then, under "Said only in the talk", come talks whose captions contain the words
(the card says which: "Said in the talk: mockery"); a search typed, or opened from a link, before the
caption words arrive waits for them, and if they fail to load the results say so and offer Try again.
When fewer than three talks have every word of a search of two or more words, up to twelve talks with
only some of the words follow (rarest words first, and any talk with a rare word in its title), the
heading says "No full matches for … but 7 talks have some of the words", and the chips and years count
those talks. Words written apart also find the word written together ("native php" finds NativePHP), a
repeated word counts once, and searched words of five or more letters also match their other endings
("organizes" finds "organization", "struggles" finds "struggling"). Every filtered view has its own URL
(`?q=&conference=&year=&speaker=`), Back undoes filter changes, and every talk has a `#permalink`
with a copy-link button. When browsing everything, a year index under the heading jumps to any year.

## The ledger

The hero draws one tick mark per talk in a column per year, oldest on the left. As you search, only
the marks that match stay red and each column's count updates — so typing `queues` or `Otwell` shows
you at a glance which years the subject belongs to. Clicking a year filters the grid to it; arrow
keys move between years. Below desktop width the ledger folds behind a toggle so search stays near
the top; the Timeline links open it.

Talks that are one segment of a longer livestream open at their start time, and their card says
when ("Starts at 49:00").

## Languages

The interface is in English at `/` and Spanish at `/es`. The header (or the phone menu) and the
footer switch between them and keep the current filters, and a reader whose browser prefers the
other language is offered it once, in that language. Talk titles and summaries stay in English and are marked `lang="en"` on other
language pages. Strings live in `lang/es.json`, and a test fails if any interface string in the views
lacks a Spanish translation. Results headings are whole sentences for each combination of filters, so
a translation can reorder them. To add a language, add it to `TalkController::LANGUAGES` and add its
JSON file. The Spanish aims at neutral Spanish («video», tú) and follows the RAE and Fundéu on
punctuation: «» quotes, «u» for «o» before an «o» sound, and no apostrophe in short years. It has been checked
against published style guides, but not yet reviewed by a native speaker.

## Data

`database/data/talks.json` is the source of truth and is read by `TalkController` on each request —
no database migration or seeding is involved. `public/talks.json` is a byte-identical copy served as
a static file for anyone who wants the raw catalog. Each record has:

| Field         | Example                                                    |
| ------------- | ---------------------------------------------------------- |
| `year`        | `2026`                                                      |
| `conference`  | `Laracon EU`                                                |
| `title`       | `State of the Frontend`                                     |
| `speaker`     | `Joe Tannenbaum`                                            |
| `summary`     | One or two sentences on what the talk covers                |
| `youtube_url` | `https://www.youtube.com/watch?v=…`                         |

Counts by conference: Laracon EU 217, Laracon US 167, Laracon Online 109, Laracon AU 83,
Laracon India 50.

Speaker credits were checked against each video's YouTube title. Summaries that only restate the
title and speaker ("Jeffrey Way on better testing in Laravel 4.") are not shown on the cards.

`database/data/talk-keywords.json` maps each talk's `youtube_url` to the 30 most distinctive words
said in it, so search can find a talk by what it covers. `scripts/talk-keywords.py` builds it from
the videos' English captions (fetched with yt-dlp; automatic captions where there are no others)
and keeps only the words. 588 of the 626 talks have captions. The page loads the words from
`GET /keywords` after it renders.

The same script also writes `database/data/talk-said.txt`: every word of three or more letters said in
any talk, with the talks that say it and how often (about 2 MB; words only, in no order). When fewer
than three talks have every searched word on the card or among their keywords, the page sends the
words to `POST /said`, which answers from this file, so a talk remembered by an anecdote ("jiro
dreams of sushi") can still be found. The words travel in the request body and are not stored.

## Running it locally

```sh
composer install
npm install
cp .env.example .env
php artisan key:generate
npm run build          # or: npm run dev
php artisan serve
```

Then open http://127.0.0.1:8000.

`.env` is gitignored — `.env.example` is the file that is committed.

## Tests

```sh
php artisan test
```

The suite checks that the homepage and `/earlier` together render all 626 talks once each, newest-first,
that filtered links render every year, that both copies of the
archive are identical, that every talk has a video URL and its own permalink, that livestream
segments show their start time, that the page sets no cookies, that `/es` serves the Spanish
interface (and unknown languages 404), and that every interface string has a Spanish translation.

## Tech stack

- Laravel 13 (PHP 8.4), SQLite configured but unused by the catalog
- Blade for the single view, one controller, `GET /{locale?}` plus `GET /earlier/{locale?}`, `GET /keywords` and `POST /said`, no auth
- Tailwind CSS 4 with a hand-written token layer, built by Vite 8
- Alpine.js 3 for client-side search and filtering
- Pest for the test suite
- Instrument Sans, Hanken Grotesk and Geist Mono, self-hosted as WOFF2 in `public/fonts`

## Design notes

The design marries Laravel and Laracasts. From Laravel: the paper background (`#FDFDFC`), Laravel
red (`#FF2D20`) and Instrument Sans for body text. From Laracasts: the navy surfaces (`#010618`,
`#0D172C`), its slate ramp, its blue (`#459FFB` on navy, `#1867D2` on paper) and heavy Hanken
Grotesk for headings. Red marks identity — the logo, the headline accent, the tick ledger and search
highlights — and blue marks everything you can act on. The hero is a Laracasts-style navy panel in
both themes; dark mode carries the navy across the whole page.

All tokens live at the top of `resources/css/app.css`, and every text colour clears WCAG AA on the
surface it sits on. The theme (System, Light or Dark) is remembered in `localStorage` and set before
first paint. Motion is short and switched off under `prefers-reduced-motion`.

The page sets no cookies and makes no third-party requests before you press play: the session and
CSRF middleware are removed from the read-only routes, and fonts are served from this site.

## Credits

An independent archive. Not affiliated with Laravel or with any Laracon organiser. Talk data was
compiled from public Laracon recordings; the videos are hosted on YouTube by their respective
conferences.
