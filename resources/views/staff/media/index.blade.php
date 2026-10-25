@extends('layouts.gsk')
@section('title', 'Images')
@section('content')
<div class="card">
    <h1>Images</h1>
    @unless ($processingAvailable)<div class="alert" role="alert">Image processing (the PHP GD extension) is not available on this server, so uploads are switched off.</div>@endunless
    <form method="POST" action="{{ route('staff.media.store') }}" enctype="multipart/form-data">
        @csrf
        <label for="file">Image file (JPEG, PNG or WebP, up to 2 MB)</label>
        <input id="file" name="file" type="file" accept="image/jpeg,image/png,image/webp" required>
        <label for="alt_text">Describe the image for people who cannot see it</label>
        <input id="alt_text" name="alt_text" type="text" required maxlength="300">
        <label for="caption">Caption (optional)</label>
        <input id="caption" name="caption" type="text" maxlength="300">
        <label for="credit_or_source">Photographer or source</label>
        <input id="credit_or_source" name="credit_or_source" type="text" required maxlength="200">
        <label><input type="checkbox" name="rights_confirmed" value="1" required> I confirm GSK has the right to use this image.</label>
        <label><input type="checkbox" name="people_consent_recorded" value="1"> People who can be identified in it have agreed (tick if it shows people).</label>
        <button type="submit">Upload</button>
    </form>
    <h2>Library</h2>
    <table><thead><tr><th scope="col">ID</th><th scope="col">Preview</th><th scope="col">Alt text</th><th scope="col">Source</th><th scope="col">People consent</th><th scope="col">Status</th><th scope="col"></th></tr></thead><tbody>
    @foreach ($media as $m)
        <tr><td>{{ $m->id }}</td><td><img src="{{ $m->url() }}" alt="" width="80"></td><td>{{ $m->alt_text }}</td><td>{{ $m->credit_or_source }}</td><td>{{ $m->people_consent_recorded ? 'recorded' : 'not recorded' }}</td><td>{{ $m->status }}</td>
            <td>@if ($m->status === 'approved')<form method="POST" action="{{ route('staff.media.retire', $m->id) }}">@csrf<button type="submit">Retire</button></form>@endif</td></tr>
    @endforeach
    </tbody></table>
    {{ $media->links() }}
</div>
@endsection
