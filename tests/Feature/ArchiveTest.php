<?php

it('renders the whole archive on the homepage', function () {
    $response = $this->get('/');

    $response->assertOk()
        ->assertSee('The Laracon Archive')
        ->assertSee('Fourteen years of Laracon', false)
        ->assertSee('id="talk-search"', false);

    expect(substr_count($response->getContent(), 'data-conference='))->toBe(626);
});

it('ships every talk in the source archive', function () {
    $talks = json_decode(file_get_contents(database_path('data/talks.json')), true);

    expect($talks)->toHaveCount(626);

    foreach ($talks as $talk) {
        expect($talk)->toHaveKeys(['year', 'conference', 'title', 'speaker', 'summary', 'youtube_url']);
        expect($talk['youtube_url'])->toStartWith('https://');
    }
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
