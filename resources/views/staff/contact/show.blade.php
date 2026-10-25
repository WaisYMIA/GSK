@extends('layouts.gsk')
@section('title', 'Contact message')
@section('content')
<div class="card">
    <h1>Contact message</h1>
    <p>From {{ $message->name }} ({{ $message->email }}), {{ $message->created_at }}</p>
    <p>Topic: {{ $message->topic ?: '(none)' }}</p>
    <p>{!! nl2br(e($message->message)) !!}</p>
    <p><a href="{{ route('staff.contact.index') }}">Back to the list</a></p>
</div>
@endsection
