@extends('layouts.gsk')
@section('title', 'Reset your password')
@section('content')
<div class="card">
    <h1>Reset your password</h1>
    <p>Enter your email address. If it is registered, we will send a reset link.</p>
    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <label for="email">Email address</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
        <button type="submit">Send reset link</button>
    </form>
</div>
@endsection
