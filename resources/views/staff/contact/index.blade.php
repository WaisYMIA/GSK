@extends('layouts.gsk')
@section('title', 'Contact messages')
@section('content')
<div class="card">
    <h1>Contact messages</h1>
    <table><thead><tr><th scope="col">Received</th><th scope="col">Topic</th><th scope="col">Status</th><th scope="col"></th></tr></thead><tbody>
    @foreach ($messages as $m)
        <tr><td>{{ $m->created_at }}</td><td>{{ $m->topic }}</td><td>{{ $m->status }}</td><td><a href="{{ route('staff.contact.show', $m->id) }}">Open</a></td></tr>
    @endforeach
    </tbody></table>
    {{ $messages->links() }}
</div>
@endsection
