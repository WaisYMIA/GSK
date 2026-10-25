@extends('layouts.gsk')
@section('title', 'Your account')
@section('content')
<div class="card">
    <h1>Your account</h1>
    <p>Signed in as {{ $user->name }}.</p>
    <p><a href="{{ route('account.security') }}">Security and two-factor authentication</a></p>
    <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Sign out</button></form>
</div>
@endsection
