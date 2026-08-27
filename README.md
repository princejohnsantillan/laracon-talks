# The Laracon Archive

A single-page Laravel site that holds every recorded Laracon talk from 2013 to 2026 — **626 talks**
across Laracon US, Laracon EU, Laracon Online, Laracon AU and Laracon India — in one searchable,
filterable table.

The whole catalog is server-rendered, so the first paint already has all 626 rows. Alpine.js filters
what is already on the page: instant search across year, conference, title, speaker and summary,
conference chips, and a year filter driven by the hero ledger.

## The ledger

The hero draws one tick mark per talk, grouped by year, newest first. As you search, only the marks
that match stay red — so typing `queues` or `Otwell` shows you at a glance which years the subject
belongs to. Clicking a year filters the table to it.

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

The suite checks that the homepage renders all 626 rows newest-first, that both copies of the
archive are identical, and that every talk has a video URL.

## Tech stack

- Laravel 13 (PHP 8.4), SQLite configured but unused by the catalog
- Blade for the single view, one controller, one `GET /` route, no auth
- Tailwind CSS 4 with a hand-written token layer, built by Vite 8
- Alpine.js 3 for client-side search and filtering
- Pest for the test suite
- Instrument Sans and Geist Mono from Google Fonts

## Design notes

Laravel red (`#FF2D20`) against laravel.com's paper (`#FDFDFC`) and a near-black dark mode, with the
theme remembered in `localStorage` and set before first paint. Instrument Sans carries the display
and body type; Geist Mono handles years, counts and labels. The red hairline under the masthead, the
conference chips and the tick ledger are the recurring marks.

## Credits

An independent archive. Not affiliated with Laravel or with any Laracon organiser. Talk data was
compiled from public Laracon recordings; the videos are hosted on YouTube by their respective
conferences.
