{{--
    Year sections, newest first. They have no accessible name, so the years are
    headings rather than fourteen extra landmarks. x-ignore keeps Alpine from walking thousands of
    card nodes that carry no directives. With $delimit, each section ends in a
    marker so the page can add the sections one at a time.
--}}
@foreach ($talksByYear as $year => $yearTalks)
<section class="year-group" id="talks-{{ $year }}" data-group-year="{{ $year }}" x-ignore>
    <header class="year-head">
        <h3 id="year-{{ $year }}">{{ $year }}</h3>
        <p data-group-count>{{ trans_choice(':count talk|:count talks', $yearTalks->count()) }}</p>
    </header>

    <ul class="cards" role="list">
        @foreach ($yearTalks as $talk)
            @include('talks.partials.card', ['talk' => $talk])
        @endforeach
    </ul>
</section>
@if ($delimit ?? false)<!--/year-->@endif
@endforeach
