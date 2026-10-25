<?php

namespace App\Support\Content;

use App\Models\ApprovalRequest;
use App\Models\ContentVersion;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use App\Support\Access\AccessDenied;
use App\Support\Access\AccessService;
use App\Support\Access\ApprovalService;
use App\Support\Access\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

// Draft, review, publish, restore and archive for pages and news posts.
// - Every change makes a new immutable version. The public site reads only the published version.
// - Nobody publishes content they wrote, and independence is judged on the person (staff profile), not the account.
// - Sensitive areas (privacy, safeguarding, donation, legal, employment) also need an independent approval,
//   which fails closed if no independent approver exists.
// - Slugs cannot change after creation. Archived content cannot be edited.
class ContentWorkflowService
{
    public function __construct(
        private readonly AccessService $access,
        private readonly AuditLogger $audit,
        private readonly ApprovalService $approvals,
        private readonly BlockValidator $validator,
    ) {
    }

    /** @param array<string, mixed> $attrs title, slug, summary, seo_title, seo_description, sensitive_area, kind, programme_id, category */
    public function create(User $actor, string $type, array $attrs, string $blocksText, string $changeNote = ''): Model
    {
        $this->requirePermission($actor, $type, 'create');
        $this->assertType($type);

        $errors = $this->checkAttributes($type, $attrs, true);
        $blocks = $this->parseBlocks($type, $blocksText, $errors);
        if ($errors !== []) {
            throw new ContentInvalid($errors);
        }

        return DB::transaction(function () use ($actor, $type, $attrs, $blocks, $changeNote) {
            $class = $this->classFor($type);
            $owner = $class::query()->create($this->ownerFields($type, $attrs) + [
                'slug' => (string) $attrs['slug'],
                'status' => 'draft',
                'created_by' => $actor->id,
            ]);
            $version = $this->newVersion($owner, $actor, $attrs, $blocks, $changeNote ?: 'First draft');
            $owner->forceFill(['current_version_id' => $version->id])->save();
            $this->audit->record('content.created', $actor, $owner, 'success', null, ['type' => $type, 'slug' => $owner->slug]);

            return $owner->refresh();
        });
    }

    public function saveDraft(User $actor, Model $owner, array $attrs, string $blocksText, string $changeNote = ''): ContentVersion
    {
        $type = $owner::contentType();
        $this->requirePermission($actor, $type, 'edit');
        if ($owner->status === 'archived') {
            throw $this->denied($actor, $owner, 'archived');
        }
        $newSensitive = (string) ($attrs['sensitive_area'] ?? $owner->sensitive_area);
        if ($owner->sensitive_area !== 'none' && $newSensitive === 'none') {
            throw $this->denied($actor, $owner, 'cannot_remove_sensitive_flag');
        }

        $attrs['sensitive_area'] = $newSensitive;
        // Anything the caller leaves out keeps its current value, so a partial edit cannot silently reset it.
        $current = $owner->currentVersion;
        foreach (['summary', 'seo_title', 'seo_description'] as $field) {
            if (! array_key_exists($field, $attrs)) {
                $attrs[$field] = $current?->{$field};
            }
        }
        if ($type === 'page') {
            $attrs += ['kind' => $owner->kind, 'programme_id' => $owner->programme_id];
        } else {
            $attrs += ['category' => $owner->category];
        }
        $errors = $this->checkAttributes($type, $attrs, false);
        $blocks = $this->parseBlocks($type, $blocksText, $errors);
        if ($errors !== []) {
            throw new ContentInvalid($errors);
        }

        return DB::transaction(function () use ($actor, $owner, $attrs, $blocks, $changeNote, $type) {
            $version = $this->newVersion($owner, $actor, $attrs, $blocks, $changeNote ?: 'Edited');
            $owner->forceFill($this->ownerFields($type, $attrs) + [
                'current_version_id' => $version->id,
                'status' => 'draft', // new content is unreviewed. The published version stays live until someone publishes again.
            ])->save();
            $this->audit->record('content.draft_saved', $actor, $owner, 'success', null, ['version' => $version->version_number]);

            return $version;
        });
    }

    public function submit(User $actor, Model $owner): Model
    {
        $this->requirePermission($actor, $owner::contentType(), 'edit');
        if ($owner->status !== 'draft') {
            throw $this->denied($actor, $owner, 'not_a_draft');
        }
        $owner->forceFill(['status' => 'in_review'])->save();
        $this->audit->record('content.submitted', $actor, $owner);

        return $owner->refresh();
    }

    public function requestSensitiveApproval(User $actor, Model $owner): ApprovalRequest
    {
        $type = $owner::contentType();
        $this->requirePermission($actor, $type, 'edit');
        if ($owner->sensitive_area === 'none') {
            throw $this->denied($actor, $owner, 'not_sensitive');
        }
        if ($owner->status !== 'in_review') {
            throw $this->denied($actor, $owner, 'not_in_review');
        }

        return $this->approvals->request($actor, 'content.sensitive_publish', $owner, 'Approval of sensitive content before publication', [
            'version_id' => $owner->current_version_id, 'owner_type' => $type,
        ]);
    }

    public function publish(User $actor, Model $owner, ?ApprovalRequest $approval = null): Model
    {
        $type = $owner::contentType();
        $this->requirePermission($actor, $type, 'publish');
        if ($owner->status !== 'in_review') {
            throw $this->denied($actor, $owner, 'not_in_review');
        }
        $version = ContentVersion::query()->find($owner->current_version_id);
        if ($version === null) {
            throw $this->denied($actor, $owner, 'no_version');
        }
        $this->assertIndependentOfAuthor($actor, $owner, $version);

        // Blocks are checked again so a retired image or a corrupted version cannot go live.
        try {
            $this->validator->validate($version->blocks ?? [], $type);
        } catch (ContentInvalid $e) {
            throw $this->denied($actor, $owner, 'content_no_longer_valid');
        }

        if ($owner->sensitive_area !== 'none') {
            $this->assertSensitiveApproval($actor, $owner, $version, $approval, $type);
        }

        return DB::transaction(function () use ($actor, $owner, $version, $approval) {
            if ($owner->sensitive_area !== 'none' && $approval !== null) {
                $approval->forceFill(['consumed_at' => now()])->save();
            }
            $owner->forceFill([
                'published_version_id' => $version->id,
                'status' => 'published',
                'published_at' => now(),
                'title' => $version->title,
            ])->save();
            $this->audit->record('content.published', $actor, $owner, 'success', null, ['version' => $version->version_number]);

            return $owner->refresh();
        });
    }

    public function restore(User $actor, Model $owner, ContentVersion $version): ContentVersion
    {
        $type = $owner::contentType();
        $this->requirePermission($actor, $type, 'edit');
        if ($version->owner_type !== $type || (int) $version->owner_id !== (int) $owner->getKey()) {
            throw $this->denied($actor, $owner, 'version_belongs_elsewhere');
        }
        if ($owner->status === 'archived') {
            throw $this->denied($actor, $owner, 'archived');
        }

        return DB::transaction(function () use ($actor, $owner, $version) {
            $copy = $this->newVersion($owner, $actor, [
                'title' => $version->title, 'summary' => $version->summary,
                'seo_title' => $version->seo_title, 'seo_description' => $version->seo_description,
            ], $version->blocks ?? [], 'Restored from version ' . $version->version_number);
            $owner->forceFill(['current_version_id' => $copy->id, 'status' => 'draft', 'title' => $copy->title])->save();
            $this->audit->record('content.restored', $actor, $owner, 'success', null, ['from_version' => $version->version_number, 'new_version' => $copy->version_number]);

            return $copy;
        });
    }

    public function archive(User $actor, Model $owner, string $reason): Model
    {
        $this->requirePermission($actor, $owner::contentType(), 'publish');
        if (trim($reason) === '') {
            throw $this->denied($actor, $owner, 'reason_required');
        }
        $owner->forceFill(['status' => 'archived', 'published_version_id' => null])->save();
        $this->audit->record('content.archived', $actor, $owner, 'success', $reason);

        return $owner->refresh();
    }

    private function assertIndependentOfAuthor(User $actor, Model $owner, ContentVersion $version): void
    {
        if ($actor->staff_profile_id === null) {
            throw $this->denied($actor, $owner, 'independence_not_established');
        }
        $author = $version->created_by !== null ? User::query()->find($version->created_by) : null;
        if ($author !== null && ($author->id === $actor->id || ($author->staff_profile_id !== null && $author->staff_profile_id === $actor->staff_profile_id))) {
            throw $this->denied($actor, $owner, 'own_content');
        }
    }

    private function assertSensitiveApproval(User $actor, Model $owner, ContentVersion $version, ?ApprovalRequest $approval, string $type): void
    {
        if ($approval === null) {
            throw $this->denied($actor, $owner, 'sensitive_approval_required');
        }
        $payload = $approval->payload ?? [];
        $matches = $approval->action_type === 'content.sensitive_publish'
            && $approval->status === 'approved'
            && $approval->consumed_at === null
            && $approval->subject_type === class_basename($owner)
            && (int) $approval->subject_id === (int) $owner->getKey()
            && ($payload['version_id'] ?? null) === $version->id
            && ($payload['owner_type'] ?? null) === $type;
        if (! $matches) {
            throw $this->denied($actor, $owner, 'sensitive_approval_not_valid');
        }
    }

    private function requirePermission(User $actor, string $type, string $action): void
    {
        if (! $this->access->can($actor, "content.{$type}s.{$action}")) {
            $this->audit->record('content.denied', $actor, null, 'denied', null, ['type' => $type, 'action' => $action, 'reason_code' => 'actor_not_authorised']);
            throw new AccessDenied('actor_not_authorised');
        }
    }

    private function denied(User $actor, Model $owner, string $code): AccessDenied
    {
        $this->audit->record('content.denied', $actor, $owner, 'denied', null, ['reason_code' => $code]);

        return new AccessDenied($code);
    }

    private function assertType(string $type): void
    {
        if (! in_array($type, ['page', 'post'], true)) {
            throw new \InvalidArgumentException("Unknown content type: {$type}");
        }
    }

    /** @return class-string<Model> */
    private function classFor(string $type): string
    {
        return $type === 'page' ? Page::class : Post::class;
    }

    private function checkAttributes(string $type, array $attrs, bool $creating): array
    {
        $errors = [];
        $title = trim((string) ($attrs['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 200) {
            $errors['title'][] = 'A title of up to 200 characters is required.';
        }
        if (! in_array((string) ($attrs['sensitive_area'] ?? 'none'), (array) config('gsk.content.sensitive_areas'), true)) {
            $errors['sensitive_area'][] = 'Unknown sensitive area.';
        }
        if (isset($attrs['seo_description']) && mb_strlen((string) $attrs['seo_description']) > 300) {
            $errors['seo_description'][] = 'The description must be 300 characters or fewer.';
        }
        if ($type === 'page' && ! in_array((string) ($attrs['kind'] ?? 'page'), (array) config('gsk.content.page_kinds'), true)) {
            $errors['kind'][] = 'Unknown page kind.';
        }
        if ($creating) {
            $slug = (string) ($attrs['slug'] ?? '');
            if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1 || strlen($slug) > 100) {
                $errors['slug'][] = 'Use lower-case letters, numbers and single hyphens only.';
            } elseif (in_array($slug, (array) config('gsk.content.reserved_slugs'), true)) {
                $errors['slug'][] = 'That address is reserved by the application.';
            } elseif ($this->classFor($type)::query()->where('slug', $slug)->exists()) {
                $errors['slug'][] = 'That address is already used.';
            }
        }

        return $errors;
    }

    private function parseBlocks(string $type, string $text, array &$errors): array
    {
        try {
            return $this->validator->validate(BlockParser::parse($text), $type);
        } catch (ContentInvalid $e) {
            foreach ($e->errors as $field => $messages) {
                foreach ($messages as $message) {
                    $errors[$field][] = $message;
                }
            }

            return [];
        }
    }

    private function ownerFields(string $type, array $attrs): array
    {
        $fields = ['title' => trim((string) $attrs['title']), 'sensitive_area' => (string) ($attrs['sensitive_area'] ?? 'none')];
        if ($type === 'page') {
            $fields['kind'] = (string) ($attrs['kind'] ?? 'page');
            $fields['programme_id'] = isset($attrs['programme_id']) && $attrs['programme_id'] !== '' ? (int) $attrs['programme_id'] : null;
        } else {
            $fields['category'] = isset($attrs['category']) && $attrs['category'] !== '' ? (string) $attrs['category'] : null;
        }

        return $fields;
    }

    private function newVersion(Model $owner, User $actor, array $attrs, array $blocks, string $note): ContentVersion
    {
        $next = (int) ContentVersion::query()
            ->where('owner_type', $owner::contentType())
            ->where('owner_id', $owner->getKey())
            ->max('version_number') + 1;

        return ContentVersion::query()->create([
            'owner_type' => $owner::contentType(),
            'owner_id' => $owner->getKey(),
            'version_number' => $next,
            'title' => trim((string) $attrs['title']),
            'summary' => $attrs['summary'] ?? null,
            'blocks' => $blocks,
            'seo_title' => $attrs['seo_title'] ?? null,
            'seo_description' => $attrs['seo_description'] ?? null,
            'created_by' => $actor->id,
            'change_note' => $note,
            'created_at' => now(),
        ]);
    }
}
