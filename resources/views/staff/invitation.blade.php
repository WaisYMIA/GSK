@extends('layouts.gsk')
@section('title', 'Accept your invitation')
@section('content')
<div class="card">
    <h1>Welcome, {{ $profile->full_name }}</h1>
    <p>Choose a password to activate your staff account. You will then set up two-factor authentication.</p>
    <form method="POST" action="{{ route('staff.invitation.accept', ['token' => $token]) }}">
        @csrf
        <label for="password">Password (at least 12 characters, with upper and lower case and a number)</label>
        <input id="password" name="password" type="password" autocomplete="new-password" required autofocus>
        <label for="password_confirmation">Confirm password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
        <button type="submit">Activate account</button>
    </form>
</div>
@endsection
