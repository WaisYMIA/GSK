<?php

namespace Tests\Feature\Content;

use App\Models\AuditEvent;
use App\Models\ContentVersion;
use App\Models\Page;
use App\Models\Post;
use App\Support\Access\ApprovalService;
use App\Support\Content\ContentInvalid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\AccessFixtures;
use Tests\TestCase;

class ContentWorkflowTest extends TestCase
{
    use RefreshDatabase, AccessFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    private function editor(string $ref = 'E1'): \App\Models\User
    {
        return $this->staffWith($ref, $this->editorKeys());
    }

    private function publisher(string $ref = 'P1'): \App\Models\User
    {
        return $this->staffWith($ref, $this->publisherKeys());
    }

    #[Test]
    public function gsk_002_a_draft_is_private_until_someone_else_publishes_it(): void
    {
        $editor = $this->editor();
        $publisher = $this->publisher();
        $page = $this->workflow()->create($editor, 'page', ['title' => 'About us', 'slug' => 'about'], "# Hello\n\nWelcome.");
        $this->get('/about')->assertNotFound();

        $this->workflow()->submit($editor, $page);
        $this->get('/about')->assertNotFound();

        $this->workflow()->publish($publisher, $page->refresh());
        $this->get('/about')->assertOk()->assertSee('Welcome.');
        $this->assertTrue(AuditEvent::query()->where('action', 'content.published')->exists());
    }

    #[Test]
    public function gsk_004_publishing_without_the_permission_is_denied_and_audited(): void
    {
        $editor = $this->editor();
        $page = $this->workflow()->create($editor, 'page', ['title' => 'About us', 'slug' => 'about'], 'Body text.');
        $this->workflow()->submit($editor, $page);

        $this->assertDeniedWith('actor_not_authorised', fn () => $this->workflow()->publish($editor, $page->refresh()));
        $this->assertTrue(AuditEvent::query()->where('action', 'content.denied')->where('outcome', 'denied')->exists());
        $this->assertNull($page->refresh()->published_version_id);
    }

    #[Test]
    public function a_person_cannot_publish_content_they_wrote_even_from_a_second_account(): void
    {
        $both = $this->staffWith('B1', array_merge($this->editorKeys(), $this->publisherKeys()));
        $page = $this->workflow()->create($both, 'page', ['title' => 'About us', 'slug' => 'about'], 'Body text.');
        $this->workflow()->submit($both, $page);
        $this->assertDeniedWith('own_content', fn () => $this->workflow()->publish($both, $page->refresh()));

        $second = $this->makeSecondAccountFor($both, 'b1-second@gsk.example.test');
        $this->grant($second, $this->makeRole('second-publisher', $this->publisherKeys()));
        $this->assertDeniedWith('own_content', fn () => $this->workflow()->publish($second, $page->refresh()));
    }

    #[Test]
    public function a_publisher_without_a_staff_profile_cannot_establish_independence(): void
    {
        $editor = $this->editor();
        $page = $this->workflow()->create($editor, 'page', ['title' => 'About us', 'slug' => 'about'], 'Body text.');
        $this->workflow()->submit($editor, $page);
        $profileless = \App\Models\User::factory()->staff()->create();
        $this->grant($profileless, $this->makeRole('profileless-publisher', $this->publisherKeys()));

        $this->assertDeniedWith('independence_not_established', fn () => $this->workflow()->publish($profileless, $page->refresh()));
    }

    #[Test]
    public function only_content_in_review_can_be_published_and_only_drafts_can_be_submitted(): void
    {
        $editor = $this->editor();
        $publisher = $this->publisher();
        $page = $this->workflow()->create($editor, 'page', ['title' => 'About us', 'slug' => 'about'], 'Body text.');

        $this->assertDeniedWith('not_in_review', fn () => $this->workflow()->publish($publisher, $page->refresh()));
        $this->workflow()->submit($editor, $page);
        $this->assertDeniedWith('not_a_draft', fn () => $this->workflow()->submit($editor, $page->refresh()));
    }

    #[Test]
    public function gsk_005_every_edit_makes_a_new_immutable_version(): void
    {
        $editor = $this->editor();
        $page = $this->workflow()->create($editor, 'page', ['title' => 'About us', 'slug' => 'about'], 'First.');
        $second = $this->workflow()->saveDraft($editor, $page, ['title' => 'About GSK'], 'Second.', 'Reworded');

        $this->assertSame(2, $second->version_number);
        $this->assertSame(2, ContentVersion::query()->where('owner_type', 'page')->where('owner_id', $page->id)->count());
        $this->assertSame($editor->id, $second->created_by);
        $this->assertSame('Reworded', $second->change_note);

        $this->expectException(LogicException::class);
        $second->forceFill(['title' => 'Tampered'])->save();
    }

    #[Test]
    public function gsk_005_versions_cannot_be_deleted(): void
    {
        $editor = $this->editor();
        $page = $this->workflow()->create($editor, 'page', ['title' => 'About us', 'slug' => 'about'], 'First.');

        $this->expectException(LogicException::class);
        ContentVersion::query()->where('owner_id', $page->id)->firstOrFail()->delete();
    }

    #[Test]
    public function gsk_145_editing_a_published_page_changes_nothing_public_until_it_is_published_again(): void
    {
        $page = $this->publishedContent('page', 'about', 'About us', 'Original text.');
        $editor = $this->editor('E9');
        $publisher = $this->publisher('P9');

        $this->workflow()->saveDraft($editor, $page->refresh(), ['title' => 'About us (new)'], 'Edited text.');
        $this->assertSame('draft', $page->refresh()->status);
        $this->get('/about')->assertOk()->assertSee('Original text.')->assertDontSee('Edited text.');

        $this->workflow()->submit($editor, $page);
        $this->workflow()->publish($publisher, $page->refresh());
        $this->get('/about')->assertOk()->assertSee('Edited text.')->assertDontSee('Original text.');
    }

    #[Test]
    public function gsk_123_restoring_copies_an_old_version_into_a_new_draft_and_keeps_history(): void
    {
        $editor = $this->editor();
        $page = $this->workflow()->create($editor, 'page', ['title' => 'About us', 'slug' => 'about'], 'Version one text.');
        $this->workflow()->saveDraft($editor, $page, ['title' => 'About us'], 'Version two text.');
        $first = ContentVersion::query()->where('owner_id', $page->id)->where('version_number', 1)->firstOrFail();

        $copy = $this->workflow()->restore($editor, $page->refresh(), $first);

        $this->assertSame(3, $copy->version_number);
        $this->assertSame($first->blocks, $copy->blocks);
        $this->assertStringContainsString('Restored from version 1', $copy->change_note);
        $this->assertSame(3, ContentVersion::query()->where('owner_id', $page->id)->count());
        $this->assertSame($copy->id, $page->refresh()->current_version_id);
        $this->assertSame('draft', $page->status);
    }

    #[Test]
    public function a_version_from_another_page_cannot_be_restored(): void
    {
        $editor = $this->editor();
        $a = $this->workflow()->create($editor, 'page', ['title' => 'A', 'slug' => 'page-a'], 'A text.');
        $b = $this->workflow()->create($editor, 'page', ['title' => 'B', 'slug' => 'page-b'], 'B text.');
        $foreign = ContentVersion::query()->where('owner_id', $b->id)->firstOrFail();

        $this->assertDeniedWith('version_belongs_elsewhere', fn () => $this->workflow()->restore($editor, $a, $foreign));
    }

    #[Test]
    public function gsk_122_sensitive_content_cannot_be_published_without_an_independent_approval(): void
    {
        $this->enableApprovals();
        $editor = $this->editor();
        $publisher = $this->publisher();
        $page = $this->workflow()->create($editor, 'page', ['title' => 'Privacy notice', 'slug' => 'privacy', 'sensitive_area' => 'privacy'], 'Placeholder text.');
        $this->workflow()->submit($editor, $page);

        $this->assertDeniedWith('sensitive_approval_required', fn () => $this->workflow()->publish($publisher, $page->refresh()));

        // No independent approver exists yet: the request is blocked and can never be used.
        $blocked = $this->workflow()->requestSensitiveApproval($editor, $page->refresh());
        $this->assertSame('blocked', $blocked->status);
        $this->assertDeniedWith('sensitive_approval_not_valid', fn () => $this->workflow()->publish($publisher, $page->refresh(), $blocked));
        $this->get('/privacy')->assertNotFound();
    }

    #[Test]
    public function gsk_122_an_approved_request_allows_publication_once_and_only_for_that_version(): void
    {
        $this->enableApprovals();
        $editor = $this->editor();
        $publisher = $this->publisher();
        $owner = $this->staffWith('OWN1', ['content.sensitive_claims.approve']);
        $page = $this->workflow()->create($editor, 'page', ['title' => 'Privacy notice', 'slug' => 'privacy', 'sensitive_area' => 'privacy'], 'Placeholder text.');
        $this->workflow()->submit($editor, $page);

        $request = $this->workflow()->requestSensitiveApproval($editor, $page->refresh());
        $this->assertSame('pending', $request->status);
        app(ApprovalService::class)->approve($owner, $request, 'Checked wording');

        $this->workflow()->publish($publisher, $page->refresh(), $request->refresh());
        $this->get('/privacy')->assertOk();
        $this->assertNotNull($request->refresh()->consumed_at);

        // A later edit is a new version. The old approval no longer covers it.
        $this->workflow()->saveDraft($editor, $page->refresh(), ['title' => 'Privacy notice', 'sensitive_area' => 'privacy'], 'Changed text.');
        $this->workflow()->submit($editor, $page->refresh());
        $this->assertDeniedWith('sensitive_approval_not_valid', fn () => $this->workflow()->publish($publisher, $page->refresh(), $request->refresh()));
    }

    #[Test]
    public function the_sensitive_flag_cannot_be_removed_by_an_edit(): void
    {
        $editor = $this->editor();
        $page = $this->workflow()->create($editor, 'page', ['title' => 'Privacy notice', 'slug' => 'privacy', 'sensitive_area' => 'privacy'], 'Placeholder text.');

        $this->assertDeniedWith('cannot_remove_sensitive_flag', fn () => $this->workflow()->saveDraft($editor, $page, ['title' => 'Privacy notice', 'sensitive_area' => 'none'], 'Text.'));
    }

    #[Test]
    public function gsk_150_reserved_malformed_and_duplicate_addresses_are_refused(): void
    {
        $editor = $this->editor();
        foreach (['login', 'staff', 'home', 'jobs', 'API'] as $slug) {
            try {
                $this->workflow()->create($editor, 'page', ['title' => 'X', 'slug' => $slug], 'Text here.');
                $this->fail("Slug {$slug} should be refused.");
            } catch (ContentInvalid $e) {
                $this->assertArrayHasKey('slug', $e->errors);
            }
        }
        foreach (['Has Space', 'under_score', '-lead', 'trail-', 'a--b'] as $slug) {
            $this->expectedInvalid(fn () => $this->workflow()->create($editor, 'page', ['title' => 'X', 'slug' => $slug], 'Text here.'));
        }
        $this->workflow()->create($editor, 'page', ['title' => 'One', 'slug' => 'unique-slug'], 'Text here.');
        $this->expectedInvalid(fn () => $this->workflow()->create($editor, 'page', ['title' => 'Two', 'slug' => 'unique-slug'], 'Text here.'));
    }

    #[Test]
    public function creating_and_editing_need_their_own_permissions(): void
    {
        $viewer = $this->staffWith('V1', ['content.pages.view']);
        $editor = $this->editor();
        $page = $this->workflow()->create($editor, 'page', ['title' => 'About us', 'slug' => 'about'], 'Body text.');

        $this->assertDeniedWith('actor_not_authorised', fn () => $this->workflow()->create($viewer, 'page', ['title' => 'X', 'slug' => 'x-page'], 'Text here.'));
        $this->assertDeniedWith('actor_not_authorised', fn () => $this->workflow()->saveDraft($viewer, $page, ['title' => 'Y'], 'Text here.'));
        $pagesOnly = $this->staffWith('V2', ['content.pages.create']);
        $this->assertDeniedWith('actor_not_authorised', fn () => $this->workflow()->create($pagesOnly, 'post', ['title' => 'Z', 'slug' => 'z-post'], 'Text here.'));
    }

    #[Test]
    public function archiving_needs_a_reason_removes_the_page_from_the_public_site_and_blocks_edits(): void
    {
        $page = $this->publishedContent('page', 'about', 'About us');
        $publisher = $this->publisher('P7');
        $editor = $this->editor('E7');
        $this->get('/about')->assertOk();

        $this->assertDeniedWith('reason_required', fn () => $this->workflow()->archive($publisher, $page, ' '));
        $this->workflow()->archive($publisher, $page, 'Out of date');

        $this->get('/about')->assertNotFound();
        $this->assertDeniedWith('archived', fn () => $this->workflow()->saveDraft($editor, $page->refresh(), ['title' => 'X'], 'Text here.'));
    }

    #[Test]
    public function news_posts_use_the_same_workflow(): void
    {
        $post = $this->publishedContent('post', 'first-story', 'First story', 'Story text.', ['category' => 'Stories']);

        $this->assertInstanceOf(Post::class, $post);
        $this->assertSame('published', $post->status);
        $this->get('/news/first-story')->assertOk()->assertSee('Story text.');
        $this->assertInstanceOf(Page::class, $this->publishedContent('page', 'second-page', 'Second page'));
    }

    #[Test]
    public function a_partial_edit_keeps_the_kind_summary_and_search_fields(): void
    {
        $editor = $this->editor();
        $page = $this->workflow()->create($editor, 'page', [
            'title' => 'Welding', 'slug' => 'welding', 'kind' => 'programme', 'summary' => 'Short summary.', 'seo_title' => 'Welding course', 'seo_description' => 'About the welding course.',
        ], 'Body text here.');

        $version = $this->workflow()->saveDraft($editor, $page, ['title' => 'Welding skills'], 'New body text.');

        $this->assertSame('programme', $page->refresh()->kind);
        $this->assertSame('Short summary.', $version->summary);
        $this->assertSame('Welding course', $version->seo_title);
        $this->assertSame('About the welding course.', $version->seo_description);
    }

    private function expectedInvalid(callable $action): void
    {
        try {
            $action();
        } catch (ContentInvalid $e) {
            $this->addToAssertionCount(1);

            return;
        }
        $this->fail('Expected ContentInvalid.');
    }
}
