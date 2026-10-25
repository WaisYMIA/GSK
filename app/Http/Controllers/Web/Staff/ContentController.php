<?php

namespace App\Http\Controllers\Web\Staff;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Models\ContentVersion;
use App\Models\Page;
use App\Models\Post;
use App\Models\Programme;
use App\Support\Access\AccessDenied;
use App\Support\Access\AccessService;
use App\Support\Content\BlockMedia;
use App\Support\Content\BlockParser;
use App\Support\Content\ContentInvalid;
use App\Support\Content\ContentWorkflowService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ContentController extends Controller
{
    private const FRIENDLY = [
        'own_content' => 'You wrote this content, so someone else must publish it.',
        'independence_not_established' => 'Your staff profile is needed to publish. Contact an administrator.',
        'sensitive_approval_required' => 'This is sensitive content. Ask for approval first, then publish after it is approved.',
        'sensitive_approval_not_valid' => 'The approval does not cover this version. Request approval again.',
        'not_in_review' => 'Only content that is in review can be published.',
        'not_a_draft' => 'Only a draft can be sent for review.',
        'content_no_longer_valid' => 'The content is no longer valid (for example an image was retired). Edit and save it again.',
        'reason_required' => 'A reason is required.',
        'not_sensitive' => 'This content is not marked sensitive.',
        'archived' => 'Archived content cannot be changed.',
        'cannot_remove_sensitive_flag' => 'The sensitive flag cannot be removed.',
    ];

    public function __construct(private readonly ContentWorkflowService $workflow, private readonly AccessService $access)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $canPages = $this->access->can($user, 'content.pages.view');
        $canPosts = $this->access->can($user, 'content.posts.view');
        abort_unless($canPages || $canPosts, 403);

        return view('staff.content.index', [
            'pages' => $canPages ? Page::query()->orderByDesc('updated_at')->limit(200)->get() : collect(),
            'posts' => $canPosts ? Post::query()->orderByDesc('updated_at')->limit(200)->get() : collect(),
            'canCreatePage' => $this->access->can($user, 'content.pages.create'),
            'canCreatePost' => $this->access->can($user, 'content.posts.create'),
        ]);
    }

    public function create(Request $request, string $type)
    {
        abort_unless($this->access->can($request->user(), "content.{$type}.create"), 403);

        return view('staff.content.form', $this->formData($type, null));
    }

    public function store(Request $request, string $type)
    {
        $single = $this->single($type);
        try {
            $owner = $this->workflow->create($request->user(), $single, $this->attrs($request, true), (string) $request->input('content', ''), (string) $request->input('change_note', ''));
        } catch (ContentInvalid $e) {
            return back()->withErrors($e->errors)->withInput();
        } catch (AccessDenied $e) {
            return $this->denied($e);
        }

        return redirect()->route('staff.content.edit', [$type, $owner->id])->with('status', 'Draft created.');
    }

    public function edit(Request $request, string $type, int $id)
    {
        $owner = $this->owner($type, $id);
        abort_unless($this->access->can($request->user(), "content.{$type}.view"), 403);

        return view('staff.content.form', $this->formData($type, $owner));
    }

    public function update(Request $request, string $type, int $id)
    {
        $owner = $this->owner($type, $id);
        try {
            $this->workflow->saveDraft($request->user(), $owner, $this->attrs($request, false), (string) $request->input('content', ''), (string) $request->input('change_note', ''));
        } catch (ContentInvalid $e) {
            return back()->withErrors($e->errors)->withInput();
        } catch (AccessDenied $e) {
            return $this->denied($e);
        }

        return redirect()->route('staff.content.edit', [$type, $id])->with('status', 'Draft saved as a new version.');
    }

    public function submit(Request $request, string $type, int $id)
    {
        return $this->act($request, $type, $id, fn (Model $o) => $this->workflow->submit($request->user(), $o), 'Sent for review.');
    }

    public function requestApproval(Request $request, string $type, int $id)
    {
        return $this->act($request, $type, $id, fn (Model $o) => $this->workflow->requestSensitiveApproval($request->user(), $o), 'Approval requested. An independent approver must approve it.');
    }

    public function publish(Request $request, string $type, int $id)
    {
        $approval = null;
        $owner = $this->owner($type, $id);
        if ($owner->sensitive_area !== 'none') {
            $approval = ApprovalRequest::query()
                ->where('action_type', 'content.sensitive_publish')->where('subject_type', class_basename($owner))->where('subject_id', $owner->id)
                ->where('status', 'approved')->whereNull('consumed_at')->orderByDesc('id')->first();
        }

        return $this->act($request, $type, $id, fn (Model $o) => $this->workflow->publish($request->user(), $o, $approval), 'Published.');
    }

    public function archive(Request $request, string $type, int $id)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:300']]);

        return $this->act($request, $type, $id, fn (Model $o) => $this->workflow->archive($request->user(), $o, $data['reason']), 'Archived. It is no longer public.');
    }

    public function versions(Request $request, string $type, int $id)
    {
        $owner = $this->owner($type, $id);
        abort_unless($this->access->can($request->user(), "content.{$type}.view"), 403);

        return view('staff.content.versions', ['type' => $type, 'owner' => $owner, 'versions' => $owner->versions()->get()]);
    }

    public function restore(Request $request, string $type, int $id, int $version)
    {
        $owner = $this->owner($type, $id);
        $target = ContentVersion::query()->findOrFail($version);
        try {
            $this->workflow->restore($request->user(), $owner, $target);
        } catch (AccessDenied $e) {
            return $this->denied($e);
        }

        return redirect()->route('staff.content.edit', [$type, $id])->with('status', 'Restored as a new draft version.');
    }

    public function preview(Request $request, string $type, int $id)
    {
        $owner = $this->owner($type, $id);
        abort_unless($this->access->can($request->user(), "content.{$type}.view"), 403);
        $version = $owner->currentVersion;
        abort_if($version === null, 404);

        return response()->view('staff.content.preview', [
            'owner' => $owner, 'version' => $version, 'media' => BlockMedia::for($version->blocks ?? []),
        ])->header('X-Robots-Tag', 'noindex, nofollow');
    }

    private function act(Request $request, string $type, int $id, callable $action, string $message)
    {
        $owner = $this->owner($type, $id);
        try {
            $action($owner);
        } catch (AccessDenied $e) {
            return $this->denied($e);
        }

        return redirect()->route('staff.content.edit', [$type, $id])->with('status', $message);
    }

    private function denied(AccessDenied $e)
    {
        if (isset(self::FRIENDLY[$e->reasonCode])) {
            return back()->withErrors(['workflow' => self::FRIENDLY[$e->reasonCode]]);
        }
        abort(403);
    }

    private function single(string $type): string
    {
        return $type === 'pages' ? 'page' : 'post';
    }

    private function owner(string $type, int $id): Model
    {
        return $type === 'pages' ? Page::query()->findOrFail($id) : Post::query()->findOrFail($id);
    }

    private function attrs(Request $request, bool $creating): array
    {
        $keys = ['title', 'summary', 'seo_title', 'seo_description', 'sensitive_area', 'kind', 'programme_id', 'category'];
        $attrs = $request->only($keys);
        if ($creating) {
            $attrs['slug'] = (string) $request->input('slug', '');
        }
        $attrs['sensitive_area'] = $attrs['sensitive_area'] ?? 'none';

        return $attrs;
    }

    private function formData(string $type, ?Model $owner): array
    {
        return [
            'type' => $type,
            'owner' => $owner,
            'content' => $owner?->currentVersion ? BlockParser::toText($owner->currentVersion->blocks ?? []) : '',
            'version' => $owner?->currentVersion,
            'programmes' => $type === 'pages' ? Programme::query()->orderBy('name')->get() : collect(),
            'areas' => config('gsk.content.sensitive_areas'),
            'kinds' => config('gsk.content.page_kinds'),
        ];
    }
}
