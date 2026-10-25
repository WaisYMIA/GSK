@extends('layouts.gsk')
@section('title', $owner ? 'Edit' : 'New')
@section('content')
@php($v = $version)
<div class="card">
    <h1>{{ $owner ? 'Edit: ' . $owner->title : ($type === 'pages' ? 'New page' : 'New news post') }}</h1>
    @if ($errors->has('workflow'))<div class="alert" role="alert">{{ $errors->first('workflow') }}</div>@endif
    @if ($owner)<p>Status: <strong>{{ $owner->status }}</strong>. {{ $owner->published_version_id ? 'A version is live.' : 'Not live.' }}
        <a href="{{ route('staff.content.preview', [$type, $owner->id]) }}">Preview</a> &middot;
        <a href="{{ route('staff.content.versions', [$type, $owner->id]) }}">History</a></p>@endif

    <form method="POST" action="{{ $owner ? route('staff.content.update', [$type, $owner->id]) : route('staff.content.store', $type) }}">
        @csrf
        <label for="title">Title</label>
        <input id="title" name="title" type="text" value="{{ old('title', $v->title ?? '') }}" required maxlength="200">
        @unless ($owner)
            <label for="slug">Address (lower-case letters, numbers and hyphens)</label>
            <input id="slug" name="slug" type="text" value="{{ old('slug') }}" required maxlength="100">
        @else
            <p>Address: <code>{{ $owner->slug }}</code> (cannot be changed)</p>
        @endunless
        @if ($type === 'pages')
            <label for="kind">Kind</label>
            <select id="kind" name="kind">@foreach ($kinds as $k)<option value="{{ $k }}" @selected(old('kind', $owner->kind ?? 'page') === $k)>{{ $k }}</option>@endforeach</select>
        @else
            <label for="category">Category (optional)</label>
            <input id="category" name="category" type="text" value="{{ old('category', $owner->category ?? '') }}" maxlength="100">
        @endif
        <label for="sensitive_area">Sensitive area (privacy, safeguarding, donation, legal, employment need independent approval)</label>
        <select id="sensitive_area" name="sensitive_area">@foreach ($areas as $a)<option value="{{ $a }}" @selected(old('sensitive_area', $owner->sensitive_area ?? 'none') === $a)>{{ $a }}</option>@endforeach</select>
        <label for="summary">Summary (shown in lists)</label>
        <textarea id="summary" name="summary" rows="2" maxlength="500">{{ old('summary', $v->summary ?? '') }}</textarea>
        <label for="seo_title">Search title (optional)</label>
        <input id="seo_title" name="seo_title" type="text" value="{{ old('seo_title', $v->seo_title ?? '') }}" maxlength="200">
        <label for="seo_description">Search description (optional, up to 300 characters)</label>
        <textarea id="seo_description" name="seo_description" rows="2" maxlength="300">{{ old('seo_description', $v->seo_description ?? '') }}</textarea>
        <label for="content">Content</label>
        <textarea id="content" name="content" rows="16" aria-describedby="content-help">{{ old('content', $content) }}</textarea>
        <p id="content-help" class="meta">Blank line between blocks. <code># Heading</code> &middot; <code>## Smaller heading</code> &middot; <code>- list item</code> &middot; <code>1. numbered item</code> &middot; <code>&gt; quote</code> &middot; <code>!image:12 align=left</code> (full, left, right, center) &middot; <code>!callout: text</code> &middot; <code>!link: Label | https://example.org</code>. No HTML. News posts can have up to 3 images.</p>
        <label for="change_note">What changed (optional)</label>
        <input id="change_note" name="change_note" type="text" maxlength="200">
        <button type="submit">Save as new draft version</button>
    </form>

    @if ($owner && $owner->status !== 'archived')
        <h2>Workflow</h2>
        @if ($owner->status === 'draft')
            <form method="POST" action="{{ route('staff.content.submit', [$type, $owner->id]) }}">@csrf<button type="submit">Send for review</button></form>
        @endif
        @if ($owner->status === 'in_review')
            @if ($owner->sensitive_area !== 'none')
                <form method="POST" action="{{ route('staff.content.request-approval', [$type, $owner->id]) }}">@csrf<button type="submit">Request independent approval</button></form>
            @endif
            <form method="POST" action="{{ route('staff.content.publish', [$type, $owner->id]) }}">@csrf<button type="submit">Publish</button></form>
        @endif
        <form method="POST" action="{{ route('staff.content.archive', [$type, $owner->id]) }}">@csrf
            <label for="reason">Reason for archiving</label><input id="reason" name="reason" type="text" required maxlength="300">
            <button type="submit">Archive (removes it from the public site)</button></form>
    @endif
</div>
@endsection
