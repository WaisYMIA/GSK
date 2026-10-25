@extends('layouts.public')
@section('title', 'Opportunities')
@section('content')
<h1>Opportunities</h1>
<p>Every vacancy listed here has been approved by GSK before it was published. Applying never costs a candidate money through GSK.</p>
<form method="GET" action="{{ route('jobs') }}">
    <label for="q">Search by job title</label>
    <input id="q" name="q" type="text" value="{{ $q }}" maxlength="100">
    <label for="country">Country code (for example KE)</label>
    <input id="country" name="country" type="text" value="{{ $country }}" maxlength="2">
    <button type="submit">Search</button>
</form>
@forelse ($vacancies as $v)
    <div class="card">
        <h2><a href="{{ route('jobs.show', $v->slug) }}">{{ $v->title }}</a></h2>
        <p class="meta">{{ $v->organisation->legal_name }} &middot; {{ $v->country_code }}@if ($v->closes_at) &middot; closes {{ $v->closes_at->format('j F Y') }}@endif</p>
    </div>
@empty
    <p>There are no open opportunities at the moment.</p>
@endforelse
{{ $vacancies->links() }}
@endsection
