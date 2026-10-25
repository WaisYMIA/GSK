<?php

namespace Tests\Feature\Content;

use App\Models\ApprovalRequest;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\AccessFixtures;
use Tests\TestCase;

class StaffContentRoutesTest extends TestCase
{
    use RefreshDatabase, AccessFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    private function pageFields(array $overrides = []): array
    {
        return array_merge([
            'title' => 'About us', 'slug' => 'about', 'kind' => 'page', 'sensitive_area' => 'none', 'content' => "# Hello\n\nBody text.",
        ], $overrides);
    }

    #[Test]
    public function gsk_146_anonymous_visitors_are_sent_to_sign_in_for_every_staff_content_route(): void
    {
        $page = $this->workflow()->create($this->staffWith('SR0', $this->editorKeys()), 'page', ['title' => 'A', 'slug' => 'page-a'], 'Text here.');

        foreach (['/staff/content', '/staff/content/pages/create', "/staff/content/pages/{$page->id}/edit", "/staff/content/pages/{$page->id}/preview", '/staff/media', '/staff/approvals'] as $path) {
            $this->get($path)->assertRedirect(route('login'));
        }
    }

    #[Test]
    public function gsk_146_staff_without_the_permission_are_refused_and_preview_needs_permission(): void
    {
        $page = $this->workflow()->create($this->staffWith('SR1', $this->editorKeys()), 'page', ['title' => 'A', 'slug' => 'page-a'], 'Text here.');
        $nobody = $this->staffWith('SR2', ['contact.messages.view']);

        $this->actingAs($nobody)->get('/staff/content')->assertForbidden();
        $this->get('/staff/content/pages/create')->assertForbidden();
        $this->get("/staff/content/pages/{$page->id}/edit")->assertForbidden();
        $this->get("/staff/content/pages/{$page->id}/preview")->assertForbidden();
        $this->post("/staff/content/pages/{$page->id}/publish")->assertForbidden();
    }

    #[Test]
    public function an_unknown_content_type_in_the_address_is_not_found(): void
    {
        $this->actingAs($this->staffWith('SR3', $this->editorKeys()))->get('/staff/content/videos/1/edit')->assertNotFound();
    }

    #[Test]
    public function gsk_085_staff_without_mfa_are_held_at_setup_before_reaching_content(): void
    {
        $editor = $this->staffWith('SR4', $this->editorKeys(), false);

        $this->actingAs($editor)->get('/staff/content')->assertRedirect(route('account.security'));
    }

    #[Test]
    public function gsk_146_preview_shows_the_draft_to_permitted_staff_and_is_not_indexed(): void
    {
        $editor = $this->staffWith('SR5', $this->editorKeys());
        $page = $this->workflow()->create($editor, 'page', ['title' => 'Draft only', 'slug' => 'draft-only'], 'Draft body text.');

        $this->actingAs($editor)->get("/staff/content/pages/{$page->id}/preview")
            ->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertSee('Preview only')->assertSee('Draft body text.');
        $this->get('/draft-only')->assertNotFound();
    }

    #[Test]
    public function an_editor_and_a_publisher_can_take_a_page_live_through_the_screens(): void
    {
        $editor = $this->staffWith('SR6', $this->editorKeys());
        $publisher = $this->staffWith('SR7', $this->publisherKeys());

        $this->actingAs($editor)->post(route('staff.content.store', 'pages'), $this->pageFields())->assertRedirect();
        $page = Page::query()->where('slug', 'about')->firstOrFail();
        $this->get(route('staff.content.edit', ['pages', $page->id]))->assertOk()->assertSee('About us');

        $this->post(route('staff.content.update', ['pages', $page->id]), $this->pageFields(['title' => 'About GSK', 'content' => 'Edited body text.']))->assertRedirect();
        $this->post(route('staff.content.submit', ['pages', $page->id]))->assertRedirect();
        $this->assertSame('in_review', $page->refresh()->status);
        $this->post(route('staff.content.publish', ['pages', $page->id]))->assertForbidden();

        $this->actingAs($publisher)->post(route('staff.content.publish', ['pages', $page->id]))->assertRedirect();
        $this->get('/about')->assertOk()->assertSee('Edited body text.');
        $this->get(route('staff.content.versions', ['pages', $page->id]))->assertOk()->assertSee('Edited');
    }

    #[Test]
    public function publishing_your_own_content_through_the_screen_shows_a_clear_message(): void
    {
        $both = $this->staffWith('SR8', array_merge($this->editorKeys(), $this->publisherKeys()));
        $this->actingAs($both)->post(route('staff.content.store', 'pages'), $this->pageFields());
        $page = Page::query()->where('slug', 'about')->firstOrFail();
        $this->post(route('staff.content.submit', ['pages', $page->id]));

        $this->post(route('staff.content.publish', ['pages', $page->id]))->assertSessionHasErrors('workflow');
        $this->get('/about')->assertNotFound();
    }

    #[Test]
    public function invalid_content_is_returned_with_field_errors_and_the_input_kept(): void
    {
        $editor = $this->staffWith('SR9', $this->editorKeys());

        $this->actingAs($editor)->post(route('staff.content.store', 'pages'), $this->pageFields(['slug' => 'login']))
            ->assertSessionHasErrors('slug')->assertSessionHasInput('title', 'About us');
        $this->post(route('staff.content.store', 'pages'), $this->pageFields(['content' => '!link: x | javascript:alert(1)']))->assertSessionHasErrors('blocks');
        $this->assertSame(0, Page::query()->count());
    }

    #[Test]
    public function gsk_122_the_approval_inbox_lets_an_independent_owner_approve_sensitive_content_and_the_page_then_goes_live(): void
    {
        $this->enableApprovals();
        $editor = $this->staffWith('SA1', $this->editorKeys());
        $publisher = $this->staffWith('SA2', $this->publisherKeys());
        $owner = $this->staffWith('SA3', ['content.sensitive_claims.approve']);

        $this->actingAs($editor)->post(route('staff.content.store', 'pages'), $this->pageFields(['title' => 'Privacy notice', 'slug' => 'privacy', 'sensitive_area' => 'privacy', 'content' => 'Placeholder text.']));
        $page = Page::query()->where('slug', 'privacy')->firstOrFail();
        $this->post(route('staff.content.submit', ['pages', $page->id]));
        $this->post(route('staff.content.request-approval', ['pages', $page->id]))->assertRedirect();
        $request = ApprovalRequest::query()->where('action_type', 'content.sensitive_publish')->firstOrFail();
        $this->assertSame('pending', $request->status);

        $this->get(route('staff.approvals.index'))->assertOk()->assertDontSee('content.sensitive_publish');

        $this->actingAs($owner)->get(route('staff.approvals.index'))->assertOk()->assertSee('content.sensitive_publish');
        $this->post(route('staff.approvals.decide', [$request->id, 'approve']), ['reason' => 'Wording checked'])->assertRedirect(route('staff.approvals.index'));
        $this->assertSame('approved', $request->refresh()->status);

        $this->actingAs($publisher)->post(route('staff.content.publish', ['pages', $page->id]))->assertRedirect();
        $this->get('/privacy')->assertOk();
    }

    #[Test]
    public function the_requester_cannot_decide_their_own_approval_through_the_screen(): void
    {
        $this->enableApprovals();
        $editor = $this->staffWith('SA4', array_merge($this->editorKeys(), ['content.sensitive_claims.approve']));
        $other = $this->staffWith('SA5', ['content.sensitive_claims.approve']);
        $this->actingAs($editor)->post(route('staff.content.store', 'pages'), $this->pageFields(['title' => 'Privacy', 'slug' => 'privacy', 'sensitive_area' => 'privacy', 'content' => 'Placeholder text.']));
        $page = Page::query()->where('slug', 'privacy')->firstOrFail();
        $this->post(route('staff.content.submit', ['pages', $page->id]));
        $this->post(route('staff.content.request-approval', ['pages', $page->id]));
        $request = ApprovalRequest::query()->firstOrFail();

        $this->post(route('staff.approvals.decide', [$request->id, 'approve']), ['reason' => 'self'])->assertSessionHasErrors('approval');
        $this->assertSame('pending', $request->refresh()->status);
    }

    #[Test]
    public function the_staff_dashboard_links_only_to_what_the_person_may_use(): void
    {
        $editor = $this->staffWith('SD1', $this->editorKeys());

        $this->actingAs($editor)->get('/staff')->assertOk()->assertSee('Pages and news')->assertSee('Images')->assertDontSee('Contact messages');
    }
}
