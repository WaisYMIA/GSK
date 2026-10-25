@extends('layouts.gsk')
@section('title', 'Choose a new password')
@section('content')
<div class="card">
    <h1>Choose a new password</h1>
    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <label for="email">Email address</label>
        <input id="email" name="email" type="email" value="{{ old('email', $request->email) }}" autocomplete="username" required>
        <label for="password">New password (at least 12 characters, with upper and lower case and a number)</label>
        <input id="password" name="password" type="password" autocomplete="new-password" required>
        <label for="password_confirmation">Confirm new password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
        <button type="submit">Reset password</button>
    </form>
</div>
@endsection
