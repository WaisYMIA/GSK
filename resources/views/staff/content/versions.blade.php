@extends('layouts.gsk')
@section('title', 'History')
@section('content')
<div class="card">
    <h1>History: {{ $owner->title }}</h1>
    <p><a href="{{ route('staff.content.edit', [$type, $owner->id]) }}">Back to editing</a></p>
    <table><thead><tr><th scope="col">Version</th><th scope="col">Title</th><th scope="col">Note</th><th scope="col">Saved</th><th scope="col">Live</th><th scope="col"></th></tr></thead><tbody>
    @foreach ($versions as $ver)
        <tr><td>{{ $ver->version_number }}</td><td>{{ $ver->title }}</td><td>{{ $ver->change_note }}</td><td>{{ $ver->created_at }}</td>
            <td>{{ $owner->published_version_id === $ver->id ? 'live' : '' }}</td>
            <td><form method="POST" action="{{ route('staff.content.restore', [$type, $owner->id, $ver->id]) }}">@csrf<button type="submit">Restore as new draft</button></form></td></tr>
    @endforeach
    </tbody></table>
</div>
@endsection
