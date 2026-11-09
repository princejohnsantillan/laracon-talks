<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

class TalkController extends Controller
{
    /**
     * Conferences in the order the archive presents them, with the short
     * label used by the filter chips and the table.
     */
    private const CONFERENCES = [
        'Laracon US' => 'US',
        'Laracon EU' => 'EU',
        'Laracon Online' => 'Online',
        'Laracon AU' => 'AU',
        'Laracon India' => 'India',
    ];

    /**
     * Words a summary may use without saying what the talk covers: framing around its title and
     * speaker ("Nuno Maduro talks about types in Laravel", "YouTube title: …", "day-two talk") and
     * praise ("An important talk no developer should miss"). See isRestatement().
     */
    private const FRAMING_WORDS = [
        'a', 'an', 'the', 'and', 'or', 'of', 'on', 'in', 'at', 'to', 'for', 'with', 'from', 'by', 'about', 's',
        'talk', 'talks', 'gives', 'presents', 'presented', 'explains', 'continues', 'his', 'her', 'their', 'its',
        'laravel', 'youtube', 'title', 'day', 'two',
        'important', 'great', 'interesting', 'should', 'must', 'miss', 'watch', 'developer', 'developers', 'every',
        'no', 'don', 't', 'know', 'find', 'out',
    ];

    /**
     * Starter searches offered under the hero, so a first-time visitor can
     * pick a subject instead of having to think of one.
     */
    private const TOPICS = ['AI', 'Testing', 'Livewire', 'Eloquent', 'Inertia', 'Performance'];

    /**
     * Interface languages, each named in itself. English lives at "/", the others
     * under their own prefix. Talk titles and summaries stay in English.
     */
    public const LANGUAGES = ['en' => 'English', 'es' => 'Español'];

    /**
     * Years the unfiltered page renders up front. The rest arrive from earlier()
     * once the page has loaded, so the first response stays small.
     */
    private const FIRST_YEARS = 2;

    /**
     * Filters in the address bar. A filtered link, or ?all, renders every year,
     * because the matching talks can be anywhere in the archive.
     */
    private const FULL_PAGE_PARAMETERS = ['q', 'conference', 'year', 'speaker', 'all'];

    /**
     * For said(): at most this many words per request; a word's forms are it plus at most
     * SAID_ENDING letters (the longest ending the page accepts, as in "organiz" + "ations"); and a
     * word with more than SAID_FORMS such forms is too short to narrow a search down.
     */
    private const SAID_WORDS = 12;

    private const SAID_ENDING = 6;

    private const SAID_FORMS = 60;

    public function index(Request $request, ?string $locale = null): View
    {
        $this->useLocale($locale);

        $talks = $this->talks();
        $talksByYear = $talks->groupBy('year');
        $partial = ! $request->hasAny(self::FULL_PAGE_PARAMETERS) && $talksByYear->count() > self::FIRST_YEARS;
        $earlier = $partial ? $talksByYear->slice(self::FIRST_YEARS) : collect();

        return view('talks.index', [
            'talksByYear' => $talksByYear,
            'renderedYears' => $partial ? $talksByYear->take(self::FIRST_YEARS) : $talksByYear,
            'earlier' => $partial ? [
                'url' => route('talks.earlier', $locale ? ['locale' => $locale] : [], absolute: false),
                'from' => $earlier->keys()->first(),
                'count' => $earlier->flatten(1)->count(),
            ] : null,
            'total' => $talks->count(),
            'speakerCount' => $this->speakerCount($talks),
            'years' => $this->years($talks),
            'conferences' => $this->conferences($talks),
            'topics' => self::TOPICS,
            'languages' => $this->languages(),
            'contentLang' => $this->contentLang(),
            'keywordsUrl' => route('talks.keywords', absolute: false),
            'saidUrl' => route('talks.said', absolute: false),
        ]);
    }

    /**
     * The year sections the unfiltered page leaves out, as an HTML fragment the page appends.
     */
    public function earlier(Request $request, ?string $locale = null): Response
    {
        $this->useLocale($locale);

        $html = view('talks.partials.groups', [
            'talksByYear' => $this->talks()->groupBy('year')->slice(self::FIRST_YEARS),
            'contentLang' => $this->contentLang(),
            'delimit' => true,
        ])->render();

        return $this->fragment($request, $html);
    }

    /**
     * Words each talk says, by permalink, so a search can find a talk by what it covers
     * ("liskov substitution") and not only by its title or summary. The page loads this
     * after it renders. The words come from database/data/talk-keywords.json, which
     * scripts/talk-keywords.py builds from the talks' English captions.
     */
    public function keywords(Request $request): Response
    {
        $path = database_path('data/talk-keywords.json');
        $byUrl = is_readable($path) ? json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR) : [];

        $keywords = $this->talks()
            ->filter(fn (array $talk): bool => ! empty($byUrl[$talk['youtube_url']]))
            ->mapWithKeys(fn (array $talk): array => [$talk['anchor'] => $byUrl[$talk['youtube_url']]]);

        return $this->fragment($request, $keywords->toJson(JSON_UNESCAPED_UNICODE), ['Content-Type' => 'application/json']);
    }

    /**
     * Which talks say each asked-for word anywhere in them, not only among their 30 keywords,
     * so "jiro sushi" finds the talk that tells that story. The page asks only when fewer than
     * three talks have every searched word on the card or among its keywords.
     *
     * The request is a JSON body {"words": ["jiro", "sushi"]} of words and word stems. The answer
     * gives, for every word said in a talk that is one of them plus at most SAID_ENDING letters
     * ("sushi", "sushis"), the talks that say it and how often: {"said": {"sushi": {"<permalink>": 3}}}.
     * The page keeps only the endings it accepts. A word nearly every talk says ("not") is listed
     * under "common"; one that begins more than SAID_FORMS different words under "many", because
     * it can't narrow anything down. The words come in the body, not the address, and are not kept.
     */
    public function said(Request $request): Response
    {
        $asked = collect((array) $request->input('words'))
            ->filter(fn (mixed $word): bool => is_string($word) && preg_match('/^[a-z0-9]{3,40}$/', $word) === 1)
            ->unique()
            ->take(self::SAID_WORDS);

        $path = database_path('data/talk-said.txt');
        $index = is_readable($path) ? (string) file_get_contents($path) : '';

        preg_match('/^@talks (.*)$/m', $index, $talkLine);
        preg_match('/^@unindexed (.*)$/m', $index, $unindexedLine);

        $urls = explode(' ', $talkLine[1] ?? '');
        $unindexed = array_flip(explode(' ', $unindexedLine[1] ?? ''));
        $anchors = $this->talks()->pluck('anchor', 'youtube_url');

        $said = [];
        $common = [];
        $many = [];

        foreach ($asked as $start) {
            if (isset($unindexed[$start])) {
                $common[] = $start;

                continue;
            }

            preg_match_all('/^('.preg_quote($start, '/').'[a-z0-9]{0,'.self::SAID_ENDING.'}) (.*)$/m', $index, $lines, PREG_SET_ORDER);

            if (count($lines) > self::SAID_FORMS) {
                $many[] = $start;

                continue;
            }

            foreach ($lines as [, $word, $postings]) {
                foreach (explode(' ', $postings) as $posting) {
                    [$position, $count] = explode(':', $posting) + [1 => '1'];
                    $anchor = $anchors[$urls[(int) $position] ?? ''] ?? null;

                    if ($anchor !== null) {
                        $said[$word][$anchor] = (int) $count;
                    }
                }
            }
        }

        $body = json_encode(['said' => (object) $said, 'common' => $common, 'many' => $many], JSON_THROW_ON_ERROR);

        return $this->fragment($request, $body, ['Content-Type' => 'application/json', 'Cache-Control' => 'no-store']);
    }

    /**
     * A response the page fetches for itself. It is not a page of its own, so it stays out of
     * search results. Some proxies compress page loads but not fetch() responses (the Amp portal
     * is one), so it is compressed here rather than left to them: /earlier's 1.26 MB of markup
     * is about 120 KB gzipped.
     *
     * @param  array<string, string>  $headers
     */
    private function fragment(Request $request, string $body, array $headers = []): Response
    {
        $response = response($body)->withHeaders($headers + [
            'Cache-Control' => 'public, max-age=600',
            'X-Robots-Tag' => 'noindex',
            'Vary' => 'Accept-Encoding',
        ]);

        if (str_contains((string) $request->header('Accept-Encoding'), 'gzip')) {
            $response->setContent(gzencode($body, 6))->header('Content-Encoding', 'gzip');
        }

        return $response;
    }

    private function useLocale(?string $locale): void
    {
        App::setLocale($locale ?? 'en');
        Number::useLocale($locale ?? 'en');
    }

    /**
     * Talk titles and summaries are English whatever the interface language.
     */
    private function contentLang(): ?string
    {
        return App::getLocale() === 'en' ? null : 'en';
    }

    /**
     * Each language's page: a relative link for navigation, and an absolute one
     * built from APP_URL for hreflang alternates.
     *
     * @return array<string, array{name: string, url: string, absolute: string}>
     */
    private function languages(): array
    {
        return collect(self::LANGUAGES)
            ->map(function (string $name, string $code): array {
                $url = route('talks.index', $code === 'en' ? [] : ['locale' => $code], absolute: false);

                return ['name' => $name, 'url' => $url, 'absolute' => rtrim((string) config('app.url'), '/').$url];
            })
            ->all();
    }

    /**
     * Every talk in the archive, newest year first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function talks(): Collection
    {
        $path = database_path('data/talks.json');

        if (! is_readable($path)) {
            throw new RuntimeException("Talk archive missing at {$path}.");
        }

        $talks = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        return collect($talks)
            ->map(fn (array $talk): array => $talk + [
                'short' => self::CONFERENCES[$talk['conference']] ?? $talk['conference'],
                'slug' => Str::slug($talk['conference']),
                'speakers' => $this->speakers($talk['speaker']),
                'blurb' => $this->isRestatement($talk) ? null : $talk['summary'],
                'anchor' => Str::slug("{$talk['year']} {$talk['conference']} {$talk['title']}"),
                ...$this->video($talk['youtube_url']),
            ])
            ->sortBy([
                ['year', 'desc'],
                ['conference', 'asc'],
                ['title', 'asc'],
            ])
            ->values()
            ->pipe($this->uniqueAnchors(...));
    }

    /**
     * Suffix repeated anchors ("…-laravel-update-2") so every talk has its own permalink.
     *
     * @param  Collection<int, array<string, mixed>>  $talks
     * @return Collection<int, array<string, mixed>>
     */
    private function uniqueAnchors(Collection $talks): Collection
    {
        $seen = [];

        return $talks->map(function (array $talk) use (&$seen): array {
            $seen[$talk['anchor']] = ($seen[$talk['anchor']] ?? 0) + 1;

            if ($seen[$talk['anchor']] > 1) {
                $talk['anchor'] .= '-'.$seen[$talk['anchor']];
            }

            return $talk;
        });
    }

    /**
     * Distinct speakers, counting each name in a shared credit separately.
     */
    private function speakerCount(Collection $talks): int
    {
        return $talks
            ->flatMap(fn (array $talk): array => $talk['speakers'])
            ->unique()
            ->count();
    }

    /**
     * The individual names in a speaker credit such as "Taylor Otwell, Dayle Rees and Shawn McCool".
     *
     * @return list<string>
     */
    private function speakers(string $credit): array
    {
        return collect(preg_split('/\s*(?:,| and | & )\s*/', $credit) ?: [])
            ->map(fn (string $name): string => trim($name))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Some summaries only restate the card: "A Laracon EU 2019 conference talk by … titled …", or
     * "Jeffrey Way on better testing in Laravel 4." under "Better Testing In Laravel 4". The card
     * leaves out a summary with no word of its own beyond the title, speaker, event and year
     * (endings aside: "requests" is "Request") and FRAMING_WORDS, rather than repeat itself.
     *
     * @param  array<string, mixed>  $talk
     */
    private function isRestatement(array $talk): bool
    {
        if (preg_match('/^A Laracon [\w ]+ \d{4} conference talk by .+ titled /u', $talk['summary'])) {
            return true;
        }

        $words = fn (string $text): array => preg_split('/[^\p{L}\p{N}]+/u', Str::lower(Str::ascii($text)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stem = fn (string $word): string => strlen($word) > 4 ? (string) preg_replace('/(ing|ed|es|s)$/', '', $word) : $word;
        $shares = fn (string $a, string $b): bool => $a === $b || (min(strlen($a), strlen($b)) >= 4 && (str_starts_with($a, $b) || str_starts_with($b, $a)));
        $card = array_map($stem, $words("{$talk['title']} {$talk['speaker']} {$talk['conference']} {$talk['year']}"));

        return collect($words($talk['summary']))
            ->reject(fn (string $word): bool => in_array($word, self::FRAMING_WORDS, true))
            ->every(fn (string $word): bool => collect($card)->contains(fn (string $known): bool => $shares($stem($word), $known)));
    }

    /**
     * The YouTube video ID behind a talk's link, and where playback starts when the
     * talk is one segment of a longer livestream.
     *
     * @return array{video_id: string|null, start: string|null}
     */
    private function video(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $seconds = isset($query['t']) ? (int) $query['t'] : 0;

        return [
            'video_id' => isset($query['v']) && is_string($query['v']) ? $query['v'] : null,
            'start' => $seconds > 0 ? $this->timestamp($seconds) : null,
        ];
    }

    /**
     * Seconds as a video timestamp: 754 becomes "12:34" and 4354 becomes "1:12:34".
     */
    private function timestamp(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $rest = $seconds % 60;

        return $hours > 0
            ? sprintf('%d:%02d:%02d', $hours, $minutes, $rest)
            : sprintf('%d:%02d', $minutes, $rest);
    }

    /**
     * Talk counts per year, newest first — the data behind the hero ledger.
     *
     * @return Collection<int, array{year: int, count: int}>
     */
    private function years(Collection $talks): Collection
    {
        return $talks
            ->countBy('year')
            ->map(fn (int $count, int $year): array => ['year' => $year, 'count' => $count])
            ->sortByDesc('year')
            ->values();
    }

    /**
     * @return Collection<int, array{label: string, short: string, slug: string, count: int}>
     */
    private function conferences(Collection $talks): Collection
    {
        $counts = $talks->countBy('conference');

        return collect(self::CONFERENCES)
            ->map(fn (string $short, string $label): array => [
                'label' => $label,
                'short' => $short,
                'slug' => Str::slug($label),
                'count' => $counts[$label] ?? 0,
            ])
            ->values();
    }
}
