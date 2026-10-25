@foreach ($blocks as $b)
    @switch($b['type'])
        @case('heading')
            @if (($b['level'] ?? 2) === 3)<h3>{{ $b['text'] }}</h3>@else<h2>{{ $b['text'] }}</h2>@endif
            @break
        @case('paragraph')
            <p>{!! nl2br(e($b['text'])) !!}</p>
            @break
        @case('list')
            @if (! empty($b['ordered']))<ol>@foreach ($b['items'] as $item)<li>{{ $item }}</li>@endforeach</ol>
            @else<ul>@foreach ($b['items'] as $item)<li>{{ $item }}</li>@endforeach</ul>@endif
            @break
        @case('quote')
            <blockquote>{!! nl2br(e($b['text'])) !!}</blockquote>
            @break
        @case('callout')
            <aside class="callout" role="note">{!! nl2br(e($b['text'])) !!}</aside>
            @break
        @case('link')
            @if (\App\Support\Content\LinkValidator::isAllowed($b['url']))
                <p><a href="{{ $b['url'] }}" @if (str_starts_with($b['url'], 'http')) rel="noopener noreferrer" @endif>{{ $b['label'] }}</a></p>
            @endif
            @break
        @case('image')
            @php($m = $media[$b['media_id']] ?? null)
            @if ($m)
                <figure class="img-{{ $b['align'] ?? 'full' }}">
                    <img src="{{ $m->url() }}" alt="{{ $m->alt_text }}" width="{{ $m->width }}" height="{{ $m->height }}" loading="lazy">
                    @if ($m->caption)<figcaption>{{ $m->caption }}@if ($m->credit_or_source) ({{ $m->credit_or_source }})@endif</figcaption>@endif
                </figure>
            @endif
            @break
    @endswitch
@endforeach
<div style="clear:both"></div>
