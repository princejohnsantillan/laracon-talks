<?php

it('renders the whole archive on the homepage', function () {
    $response = $this->get('/');

    $response->assertOk()
        ->assertSee('The Laracon Archive')
        ->assertSee('Fourteen years of Laracon', false)
        ->assertSee('id="talk-search"', false)
        ->assertSee('scope="rowgroup"', false)
        ->assertSeeInOrder([
            'href="#talk-search"',
            '<main ',
            'id="talk-search"',
            '<table ',
            '</table>',
            '</main>',
        ], false);

    expect(substr_count($response->getContent(), 'data-conference='))->toBe(626);
});

it('omits placeholder descriptions without omitting their talks', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Your First Pull Request')
        ->assertDontSee('A Laracon EU 2025 conference talk by');
});

it('uses local lazy posters and keeps unavailable and timestamped talks watchable', function () {
    $content = $this->get('/')->assertOk()->getContent();
    $document = new DOMDocument;
    @$document->loadHTML($content);
    $xpath = new DOMXPath($document);
    $links = $xpath->query('//a[@class="talk-link"]');

    expect($links->length)->toBe(626)
        ->and($xpath->query('//tr[@data-conference]//a')->length)->toBe(626);

    foreach ($xpath->query('//a[@class="talk-link"]//img') as $image) {
        expect($image->getAttribute('src'))->toStartWith(asset('thumbnails/'))
            ->and($image->getAttribute('loading'))->toBe('lazy')
            ->and($image->getAttribute('alt'))->toBe('');
    }

    $shared = $xpath->query('//a[@class="talk-link" and contains(@href, "cucIWpAenro")]');
    expect($shared->length)->toBe(4);
    foreach ($shared as $link) {
        expect($xpath->query('.//img', $link)->item(0)->getAttribute('src'))
            ->toBe(asset('thumbnails/cucIWpAenro.webp'));
    }

    $unavailable = $xpath->query('//a[@class="talk-link" and contains(@href, "0WqSvhQ31MQ")]')->item(0);
    expect($unavailable->getAttribute('aria-label'))->toBe('Watch “Laravel Pulse: Behind the Scenes” on YouTube (opens in a new tab)')
        ->and($xpath->query('.//img', $unavailable)->length)->toBe(0);
});

it('ships every talk in the source archive', function () {
    $talks = json_decode(file_get_contents(database_path('data/talks.json')), true);

    expect($talks)->toHaveCount(626);

    foreach ($talks as $talk) {
        expect($talk)->toHaveKeys(['year', 'conference', 'title', 'speaker', 'summary', 'youtube_url']);
        expect($talk['youtube_url'])->toStartWith('https://');
    }

    $pest = collect($talks)->firstWhere('youtube_url', 'https://www.youtube.com/watch?v=lEvau6CgqPE');

    expect($pest['title'])->toBe('Introducing Pest')
        ->and($pest['speaker'])->toBe('Nuno Maduro');
});

it('keeps the public copy of the archive in sync', function () {
    expect(md5_file(public_path('talks.json')))->toBe(md5_file(database_path('data/talks.json')));
});

it('lists the newest talks first', function () {
    $content = $this->get('/')->getContent();

    preg_match_all('/data-year="(\d{4})"/', $content, $matches);

    $years = array_map('intval', $matches[1]);

    expect($years)->toHaveCount(626)
        ->and($years[0])->toBe(2026)
        ->and($years)->toEqual(collect($years)->sortDesc()->values()->all());
});

it('covers all five Laracons', function () {
    $content = $this->get('/')->getContent();

    foreach (['laracon-us', 'laracon-eu', 'laracon-online', 'laracon-au', 'laracon-india'] as $slug) {
        expect($content)->toContain('data-conference="'.$slug.'"');
    }
});
