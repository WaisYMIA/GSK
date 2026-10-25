@extends('layouts.gsk')
@section('title', 'Preview: ' . $version->title)
@section('content')
<div class="alert" role="note"><strong>Preview only.</strong> This is the current draft. It is not public and search engines are told not to index it.</div>
<div class="card">
    <h1>{{ $version->title }}</h1>
    @include('partials.blocks', ['blocks' => $version->blocks ?? [], 'media' => $media])
</div>
@endsection
