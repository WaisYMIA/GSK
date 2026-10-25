@extends('layouts.public')
@section('title', $vacancy->title)
@section('content')
<article>
    <h1>{{ $vacancy->title }}</h1>
    <p class="meta">{{ $vacancy->organisation->legal_name }} &middot; {{ $vacancy->country_code }}@if ($vacancy->closes_at) &middot; closes {{ $vacancy->closes_at->format('j F Y') }}@endif</p>
    <p>{!! nl2br(e($vacancy->description)) !!}</p>
    <p>Applications open with candidate accounts, which arrive in a later release.</p>
</article>
@endsection
