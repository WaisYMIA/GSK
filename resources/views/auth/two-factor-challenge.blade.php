@extends('layouts.gsk')
@section('title', 'Two-factor authentication')
@section('content')
<div class="card">
    <h1>Two-factor authentication</h1>
    <form method="POST" action="{{ route('two-factor.login') }}">
        @csrf
        <label for="code">Code from your authenticator app</label>
        <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" autofocus>
        <button type="submit">Continue</button>
    </form>
    <hr>
    <form method="POST" action="{{ route('two-factor.login') }}">
        @csrf
        <label for="recovery_code">Or use a recovery code</label>
        <input id="recovery_code" name="recovery_code" type="text" autocomplete="off">
        <button type="submit">Use recovery code</button>
    </form>
</div>
@endsection
