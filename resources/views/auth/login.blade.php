@extends('layouts.gsk')
@section('title', 'Sign in')
@section('content')
<div class="card">
    <h1>Sign in</h1>
    <form method="POST" action="{{ route('login') }}">
        @csrf
        <label for="email">Email address</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        <button type="submit">Sign in</button>
    </form>
    <p><a href="{{ route('password.request') }}">Forgot your password?</a></p>
</div>
@endsection
