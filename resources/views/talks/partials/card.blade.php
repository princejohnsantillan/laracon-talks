{{-- One talk. Rendered 626 times, so the markup stays flat and lean. --}}
<li>
<article class="card" id="{{ $talk['anchor'] }}" aria-labelledby="title-{{ $talk['anchor'] }}" data-talk data-conference="{{ $talk['slug'] }}" data-year="{{ $talk['year'] }}" data-speakers="{{ implode('|', $talk['speakers']) }}">
<a class="card-media tone-bg-{{ $talk['slug'] }}" href="{{ $talk['youtube_url'] }}" target="_blank" rel="noreferrer" tabindex="-1" aria-hidden="true">
<span class="card-media-fallback">{{ $talk['conference'] }} {{ $talk['year'] }}</span>
@if ($talk['video_id'])
<img src="https://i.ytimg.com/vi/{{ $talk['video_id'] }}/mqdefault.jpg" srcset="https://i.ytimg.com/vi/{{ $talk['video_id'] }}/mqdefault.jpg 320w, https://i.ytimg.com/vi/{{ $talk['video_id'] }}/hqdefault.jpg 480w" sizes="(min-width: 1024px) 400px, (min-width: 640px) 50vw, 100vw" alt="" loading="lazy" decoding="async" width="320" height="180">
@endif
<span class="play"></span>
@if ($talk['start'])
<span class="stamp">{{ __('Starts at :time', ['time' => $talk['start']]) }}</span>
@endif
</a>
<div class="card-body">
<p class="card-meta"><span class="conf tone-{{ $talk['slug'] }}" data-index>{{ $talk['conference'] }}</span><span class="card-year" data-index>{{ $talk['year'] }}</span></p>
<h4 class="card-title" id="title-{{ $talk['anchor'] }}"@if ($contentLang) lang="{{ $contentLang }}"@endif><a href="{{ $talk['youtube_url'] }}" target="_blank" rel="noreferrer" tabindex="-1"><span data-index data-mark>{{ $talk['title'] }}</span></a></h4>
<p class="card-speakers"><span class="sr-only">{{ __('Speakers:') }} </span>@foreach ($talk['speakers'] as $name)@unless ($loop->first)<span aria-hidden="true">{{ $loop->last ? ' & ' : ', ' }}</span>@endunless<button type="button" class="speaker" data-action="speaker" data-speaker="{{ $name }}" title="{{ __('Show all talks by :name', ['name' => $name]) }}" aria-describedby="speaker-hint"><span data-index data-mark>{{ $name }}</span></button>@endforeach</p>
@if ($talk['blurb'])
<p class="card-summary" id="summary-{{ $talk['anchor'] }}"@if ($contentLang) lang="{{ $contentLang }}"@endif data-index data-mark data-summary>{{ $talk['blurb'] }}</p>
<button type="button" class="more" data-action="more" aria-expanded="false" aria-controls="summary-{{ $talk['anchor'] }}" hidden>{{ __('Show more') }}</button>
@endif
</div>
<footer class="card-actions">
<a class="watch" href="{{ $talk['youtube_url'] }}" target="_blank" rel="noreferrer">{{ __('Watch') }} <span class="sr-only">{{ __('“:title” on YouTube (opens in a new tab)', ['title' => $talk['title']]) }}</span></a>
<button type="button" class="copy-link" data-action="copy">{{ __('Copy link') }} <span class="sr-only">{{ __('to “:title”', ['title' => $talk['title']]) }}</span></button>
</footer>
</article>
</li>
