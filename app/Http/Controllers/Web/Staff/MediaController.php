<?php

namespace App\Http\Controllers\Web\Staff;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Support\Access\AccessService;
use App\Support\Access\AuditLogger;
use App\Support\Content\ContentInvalid;
use App\Support\Content\MediaProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    private const PERMISSION = 'content.posts.manage_media';

    public function index(Request $request, AccessService $access, MediaProcessor $processor)
    {
        abort_unless($access->can($request->user(), self::PERMISSION), 403);

        return view('staff.media.index', ['media' => Media::query()->orderByDesc('id')->paginate(20), 'processingAvailable' => $processor->available()]);
    }

    public function store(Request $request, AccessService $access, MediaProcessor $processor, AuditLogger $audit)
    {
        abort_unless($access->can($request->user(), self::PERMISSION), 403);
        $max = (int) config('gsk.media.max_kilobytes', 2048);
        $data = $request->validate([
            'file' => ['required', 'file', "max:{$max}"],
            'alt_text' => ['required', 'string', 'max:300'],
            'credit_or_source' => ['required', 'string', 'max:200'],
            'rights_confirmed' => ['accepted'],
            'caption' => ['nullable', 'string', 'max:300'],
            'people_consent_recorded' => ['nullable', 'boolean'],
        ]);

        try {
            $result = $processor->process((string) file_get_contents($request->file('file')->getRealPath()));
        } catch (ContentInvalid $e) {
            return back()->withErrors($e->errors)->withInput();
        }

        // The stored name is generated. The uploaded file name is never used or kept.
        $path = 'media/' . Str::uuid() . '.' . $result['extension'];
        Storage::disk('public')->put($path, $result['contents']);
        $media = Media::query()->create([
            'uploaded_by' => $request->user()->id,
            'stored_path' => $path,
            'mime' => $result['mime'],
            'size_bytes' => strlen($result['contents']),
            'width' => $result['width'],
            'height' => $result['height'],
            'alt_text' => $data['alt_text'],
            'caption' => $data['caption'] ?? null,
            'credit_or_source' => $data['credit_or_source'],
            'rights_confirmed' => true,
            'people_consent_recorded' => (bool) ($data['people_consent_recorded'] ?? false),
            'status' => 'approved',
        ]);
        $audit->record('media.uploaded', $request->user(), $media);

        return redirect()->route('staff.media.index')->with('status', 'Image uploaded. Use it in content with !image:' . $media->id);
    }

    public function retire(Request $request, int $id, AccessService $access, AuditLogger $audit)
    {
        abort_unless($access->can($request->user(), self::PERMISSION), 403);
        $media = Media::query()->findOrFail($id);
        $media->forceFill(['status' => 'retired'])->save();
        $audit->record('media.retired', $request->user(), $media);

        return redirect()->route('staff.media.index')->with('status', 'Image retired. Content that uses it cannot be published until it is edited.');
    }
}
