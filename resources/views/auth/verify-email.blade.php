@extends('layouts.gsk')
@section('title', 'Verify your email')
@section('content')
<div class="card">
    <h1>Verify your email address</h1>
    <p>We sent a verification link to your email address. Open it to continue.</p>
    <form method="POST" action="{{ route('verification.send') }}">@csrf<button type="submit">Resend the link</button></form>
    <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Sign out</button></form>
</div>
@endsection
