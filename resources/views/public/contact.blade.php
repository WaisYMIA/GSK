@extends('layouts.public')
@section('title', 'Contact us')
@section('content')
<h1>Contact us</h1>
<div class="callout" role="note">Please do not send passport or identity details, bank details or passwords here. To report a concern about safety or exploitation, read our <a href="{{ route('page', 'safeguarding') }}">safeguarding page</a>. This form is not an emergency service.</div>
<form method="POST" action="{{ route('contact.store') }}">
    @csrf
    <label for="name">Your name</label>
    <input id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" required>
    <label for="email">Email address</label>
    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>
    <label for="topic">Topic (optional)</label>
    <input id="topic" name="topic" type="text" value="{{ old('topic') }}">
    <label for="message">Message</label>
    <textarea id="message" name="message" rows="6" required minlength="10" maxlength="3000">{{ old('message') }}</textarea>
    <div class="hp" aria-hidden="true"><label for="website">Leave this empty</label><input id="website" name="website" type="text" tabindex="-1" autocomplete="off"></div>
    <button type="submit">Send message</button>
</form>
@endsection
