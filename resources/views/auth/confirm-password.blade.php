@extends('layouts.gsk')
@section('title', 'Confirm your password')
@section('content')
<div class="card">
    <h1>Confirm your password</h1>
    <p>This is a secure area. Confirm your password to continue.</p>
    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required autofocus>
        <button type="submit">Confirm</button>
    </form>
</div>
@endsection
