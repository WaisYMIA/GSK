@extends('layouts.gsk')
@section('title', 'Approvals')
@section('content')
<div class="card">
    <h1>Approvals waiting for you</h1>
    <p>You only see requests that you are allowed to decide and that someone else made.</p>
    @forelse ($requests as $r)
        <div class="card">
            <p><strong>{{ $r->action_type }}</strong> on {{ $r->subject_type }} {{ $r->subject_id }}<br>Requested by {{ $r->requester->name ?? 'unknown' }}: {{ $r->reason }}</p>
            <form method="POST" action="{{ route('staff.approvals.decide', [$r->id, 'approve']) }}">@csrf
                <label for="a{{ $r->id }}">Your reason</label><input id="a{{ $r->id }}" name="reason" type="text" required maxlength="300"><button type="submit">Approve</button></form>
            <form method="POST" action="{{ route('staff.approvals.decide', [$r->id, 'reject']) }}">@csrf
                <label for="r{{ $r->id }}">Your reason</label><input id="r{{ $r->id }}" name="reason" type="text" required maxlength="300"><button type="submit">Reject</button></form>
        </div>
    @empty
        <p>Nothing is waiting for you.</p>
    @endforelse
</div>
@endsection
