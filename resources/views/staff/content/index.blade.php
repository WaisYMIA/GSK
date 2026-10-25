@extends('layouts.gsk')
@section('title', 'Pages and news')
@section('content')
<div class="card">
    <h1>Pages and news</h1>
    @if ($canCreatePage)<a href="{{ route('staff.content.create', 'pages') }}">New page</a> @endif
    @if ($canCreatePost)<a href="{{ route('staff.content.create', 'posts') }}">New news post</a>@endif
    @if ($pages->isNotEmpty())
        <h2>Pages</h2>
        <table><thead><tr><th scope="col">Title</th><th scope="col">Address</th><th scope="col">Kind</th><th scope="col">Status</th><th scope="col">Live</th></tr></thead><tbody>
        @foreach ($pages as $p)
            <tr><td><a href="{{ route('staff.content.edit', ['pages', $p->id]) }}">{{ $p->title }}</a></td><td>{{ $p->slug }}</td><td>{{ $p->kind }}</td><td>{{ $p->status }}</td><td>{{ $p->published_version_id ? 'yes' : 'no' }}</td></tr>
        @endforeach
        </tbody></table>
    @endif
    @if ($posts->isNotEmpty())
        <h2>News posts</h2>
        <table><thead><tr><th scope="col">Title</th><th scope="col">Address</th><th scope="col">Category</th><th scope="col">Status</th><th scope="col">Live</th></tr></thead><tbody>
        @foreach ($posts as $p)
            <tr><td><a href="{{ route('staff.content.edit', ['posts', $p->id]) }}">{{ $p->title }}</a></td><td>{{ $p->slug }}</td><td>{{ $p->category }}</td><td>{{ $p->status }}</td><td>{{ $p->published_version_id ? 'yes' : 'no' }}</td></tr>
        @endforeach
        </tbody></table>
    @endif
</div>
@endsection
