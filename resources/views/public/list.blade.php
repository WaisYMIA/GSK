@extends('layouts.public')
@section('title', $heading)
@section('content')
<h1>{{ $heading }}</h1>
@if ($categories->isNotEmpty())
    <nav aria-label="Categories"><a href="{{ url()->current() }}">All</a>
        @foreach ($categories as $c) &middot; <a href="{{ url()->current() }}?category={{ urlencode($c) }}" @if ($category === $c) aria-current="true" @endif>{{ $c }}</a>@endforeach
    </nav>
@endif
@forelse ($items as $item)
    <div class="card">
        <h2><a href="{{ $item['url'] }}">{{ $item['title'] }}</a></h2>
        @if ($item['date'])<p class="meta">{{ $item['date']->format('j F Y') }}</p>@endif
        @if ($item['summary'])<p>{{ $item['summary'] }}</p>@endif
    </div>
@empty
    <p>{{ $empty }}</p>
@endforelse
{{ $items->links() }}
@endsection
