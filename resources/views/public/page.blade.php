@extends('layouts.public')
@section('title', $version->seo_title ?: $version->title)
@if ($version->seo_description)@section('description', $version->seo_description)@endif
@section('content')
<article>
    <h1>{{ $version->title }}</h1>
    @if ($date)<p class="meta">Published {{ $date->format('j F Y') }}</p>@endif
    @include('partials.blocks', ['blocks' => $version->blocks ?? [], 'media' => $media])
</article>
@endsection
