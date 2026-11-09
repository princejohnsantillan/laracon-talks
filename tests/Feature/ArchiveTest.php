<?php

it('renders the newest two years first and serves the rest from /earlier', function () {
    $response = $this->get('/');

    $response->assertOk()
        ->assertSee('The Laracon Archive')
        ->assertSee('Fourteen years of Laracon', false)
        ->assertSee('id="talk-search"', false)
        ->assertSee('href="?all#talks-2024"', false);

    $earlier = $this->get('/earlier');

    $earlier->assertOk()->assertHeader('X-Robots-Tag', 'noindex');
    expect($earlier->headers->getCookies())->toBeEmpty();

    $cards = '/<article class="card" id="([^"]+)"[^>]* data-year="(\d{4})"/';
    preg_match_all($cards, $response->getContent(), $first);
    preg_match_all($cards, $earlier->getContent(), $rest);
    preg_match_all($cards, $this->get('/?all')->getContent(), $all);

    // Together they are the whole archive, once each, in the full page's order.
    expect(array_values(array_unique($first[2])))->toBe(['2026', '2025'])
        ->and(array_merge($first[1], $rest[1]))->toHaveCount(626)
        ->toBe($all[1]);

    // The page fetches the rest with fetch(), which some proxies leave uncompressed, so the app gzips it.
    $gzipped = $this->get('/earlier', ['Accept-Encoding' => 'gzip, br']);

    $gzipped->assertOk()->assertHeader('Content-Encoding', 'gzip');
    expect(gzdecode($gzipped->getContent()))->toBe($earlier->getContent())
        ->and(strlen($gzipped->getContent()))->toBeLessThan(strlen($earlier->getContent()) / 5);
});

it('renders every year when the link carries a filter', function (string $query) {
    expect(substr_count($this->get("/?{$query}")->getContent(), 'data-conference='))->toBe(626);
})->with(['all', 'q=livewire', 'year=2013', 'speaker=Taylor%20Otwell', 'conference=laracon-eu']);

it('ships every talk in the source archive', function () {
    $talks = json_decode(file_get_contents(database_path('data/talks.json')), true);

    expect($talks)->toHaveCount(626);

    foreach ($talks as $talk) {
        expect($talk)->toHaveKeys(['year', 'conference', 'title', 'speaker', 'summary', 'youtube_url']);
        expect($talk['youtube_url'])->toStartWith('https://');
    }
});

it('leaves out summaries that only restate the card', function () {
    $content = $this->get('/?all')->getContent();

    // Nothing beyond the title, speaker and event, even with another word ending ("requests" for "Request").
    expect($content)->not->toContain('Jeffrey Way on better testing in Laravel 4.')
        ->not->toContain('Kenny Meyers on requests in Laravel 4.')
        ->not->toContain('conference talk by')
        // Or nothing but praise.
        ->not->toContain('An important talk no developer should miss.')
        // A summary that says what the talk covers stays.
        ->toContain('Taylor Otwell presents the new features in Laravel 5.3 at Laracon.');
});

it('keeps the public copy of the archive in sync', function () {
    expect(md5_file(public_path('talks.json')))->toBe(md5_file(database_path('data/talks.json')));
});

it('lists the newest talks first', function () {
    $content = $this->get('/?all')->getContent();

    preg_match_all('/data-year="(\d{4})"/', $content, $matches);

    $years = array_map('intval', $matches[1]);

    expect($years)->toHaveCount(626)
        ->and($years[0])->toBe(2026)
        ->and($years)->toEqual(collect($years)->sortDesc()->values()->all());
});

it('covers all five Laracons', function () {
    $content = $this->get('/?all')->getContent();

    foreach (['laracon-us', 'laracon-eu', 'laracon-online', 'laracon-au', 'laracon-india'] as $slug) {
        expect($content)->toContain('data-conference="'.$slug.'"');
    }
});

it('serves the words said in each talk by permalink', function () {
    preg_match_all('/<article class="card" id="([^"]+)"/', $this->get('/?all')->getContent(), $cards);

    $response = $this->get('/keywords');
    $keywords = $response->assertOk()->assertHeader('X-Robots-Tag', 'noindex')->json();

    // Keyed by the cards' permalinks, so the page can attach them; most talks have captions.
    expect($response->headers->getCookies())->toBeEmpty()
        ->and(array_diff(array_keys($keywords), $cards[1]))->toBeEmpty()
        ->and(count($keywords))->toBeGreaterThan(500)
        ->and($keywords)->each->toMatch('/^[a-z0-9]+( [a-z0-9]+)*$/');

    $this->get('/keywords', ['Accept-Encoding' => 'gzip'])->assertHeader('Content-Encoding', 'gzip');
});

it('finds which talks say a word anywhere in them', function () {
    preg_match_all('/<article class="card" id="([^"]+)"/', $this->get('/?all')->getContent(), $cards);

    $response = $this->postJson('/said', ['words' => ['jiro', 'test', 'not', 'con', '.*', 'A B', 5]]);
    $answer = $response->assertOk()->assertHeader('X-Robots-Tag', 'noindex')->json();

    // Taylor Otwell's sushi-chef story is said in The Tao of Laravel, not on its card.
    expect($response->headers->getCookies())->toBeEmpty()
        ->and($answer['said']['jiro'])->toHaveKey('2015-laracon-eu-the-tao-of-laravel')
        ->and(array_diff(array_keys($answer['said']['jiro']), $cards[1]))->toBeEmpty()
        // A word's forms are its endings, not longer words that start with it.
        ->and($answer['said'])->toHaveKeys(['test', 'testing'])
        ->and($answer['said'])->not->toHaveKey('testimonials')
        // Said in nearly every talk, or too short to narrow anything down; anything else is ignored.
        ->and($answer['common'])->toBe(['not'])
        ->and($answer['many'])->toBe(['con'])
        ->and(array_keys($answer['said']))->each->toMatch('/^(jiro|test)[a-z0-9]*$/');
});

it('gives every talk its own permalink', function () {
    $content = $this->get('/?all')->getContent();

    preg_match_all('/<article class="card" id="([^"]+)"/', $content, $matches);

    expect($matches[1])->toHaveCount(626)
        ->and(array_unique($matches[1]))->toHaveCount(626)
        ->and($content)->toContain('id="2022-laracon-online-laravel-update-2"');
});

it('shows where a livestream segment starts', function () {
    $content = $this->get('/?all')->getContent();

    // https://www.youtube.com/watch?v=K0ldExIZ0oc&t=2940s is 49 minutes into the stream.
    expect($content)->toContain('Starts at 49:00')
        ->and($content)->not->toContain('Starts at 0:00');
});

it('serves the archive without setting cookies', function () {
    $response = $this->get('/');

    $response->assertOk();

    expect($response->headers->getCookies())->toBeEmpty();
});

it('serves the interface in Spanish at /es', function () {
    $response = $this->get('/es');

    $response->assertOk()
        ->assertSee('<html lang="es"', false)
        ->assertSee('Catorce años de Laracon', false)
        ->assertSee('hreflang="en"', false);

    $content = $response->getContent();

    // The catalog is the same, and the English titles are marked as English, in the first years and the rest.
    $earlier = $this->get('/earlier/es')->assertOk()->getContent();
    $titles = '/<h4 class="card-title" id="title-[^"]+" lang="en">/';

    expect(substr_count($content.$earlier, 'data-conference='))->toBe(626)
        ->and(preg_match_all($titles, $content.$earlier))->toBe(626)
        ->and(substr_count($earlier, 'charlas'))->toBeGreaterThan(0);

    // On the English page the titles need no language of their own.
    expect($this->get('/?all')->getContent())->not->toContain('" lang="en">');
});

it('only serves interface languages that ship', function () {
    $this->get('/fr')->assertNotFound();
    $this->get('/earlier/fr')->assertNotFound();
});

it('has a Spanish translation for every interface string', function () {
    $spanish = json_decode(file_get_contents(lang_path('es.json')), true, flags: JSON_THROW_ON_ERROR);

    $keys = collect(glob(resource_path('views/talks/{,partials/}*.blade.php'), GLOB_BRACE))
        ->flatMap(function (string $view): array {
            preg_match_all("/(?:__|trans_choice)\('((?:[^'\\\\]|\\\\.)*)'/", file_get_contents($view), $matches);

            return array_map('stripslashes', $matches[1]);
        })
        ->unique();

    expect($keys->count())->toBeGreaterThan(100)
        ->and($keys->reject(fn (string $key): bool => array_key_exists($key, $spanish))->values()->all())->toBe([]);
});
