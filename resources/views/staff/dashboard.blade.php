@extends('layouts.gsk')
@section('title', 'Staff')
@section('content')
<div class="card">
    <h1>Staff area</h1>
    <p>Signed in as {{ $user->name }}.</p>
    <ul>
        @if ($canContent)<li><a href="{{ route('staff.content.index') }}">Pages and news</a></li>@endif
        @if ($canMedia)<li><a href="{{ route('staff.media.index') }}">Images</a></li>@endif
        @if ($canContact)<li><a href="{{ route('staff.contact.index') }}">Contact messages</a></li>@endif
        <li><a href="{{ route('staff.approvals.index') }}">Approvals waiting for you</a></li>
        <li><a href="{{ route('account.security') }}">Security and two-factor authentication</a></li>
    </ul>
</div>
@endsection
