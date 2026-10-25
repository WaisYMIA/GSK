<?php

namespace Tests\Feature\Content;

use App\Models\Vacancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\AccessFixtures;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase, AccessFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    #[Test]
    public function gsk_001_the_home_page_shows_a_neutral_message_until_a_home_page_is_published(): void
    {
        $this->get('/')->assertOk()->assertSee('being set up');

        $this->publishedContent('page', 'home', 'Welcome to GSK', 'Synthetic home text.');
        $this->get('/')->assertOk()->assertSee('Synthetic home text.');
        $this->get('/home')->assertNotFound();
    }

    #[Test]
    public function gsk_056_a_published_page_resolves_by_its_address_and_an_unknown_one_is_not_found(): void
    {
        $this->publishedContent('page', 'about', 'About us', 'About text.');
        $this->publishedContent('page', 'privacy', 'Privacy', 'Policy text.', ['kind' => 'policy']);

        $this->get('/about')->assertOk()->assertSee('About text.');
        $this->get('/privacy')->assertOk();
        $this->get('/no-such-page')->assertNotFound();
    }

    #[Test]
    public function gsk_057_drafts_and_pages_in_review_are_not_public(): void
    {
        $editor = $this->staffWith('PS1', $this->editorKeys());
        $page = $this->workflow()->create($editor, 'page', ['title' => 'Secret plan', 'slug' => 'secret-plan'], 'Hidden text.');
        $this->get('/secret-plan')->assertNotFound();

        $this->workflow()->submit($editor, $page);
        $this->get('/secret-plan')->assertNotFound();
    }

    #[Test]
    public function gsk_073_programme_pages_are_listed_and_served_under_programmes_only(): void
    {
        $this->publishedContent('page', 'welding-skills', 'Welding skills', 'Programme text.', ['kind' => 'programme', 'summary' => 'A short programme summary.']);
        $editor = $this->staffWith('PS2', $this->editorKeys());
        $this->workflow()->create($editor, 'page', ['title' => 'Draft programme', 'slug' => 'draft-programme', 'kind' => 'programme'], 'Draft text.');

        $this->get('/programmes')->assertOk()->assertSee('Welding skills')->assertSee('A short programme summary.')->assertDontSee('Draft programme');
        $this->get('/programmes/welding-skills')->assertOk()->assertSee('Programme text.');
        $this->get('/welding-skills')->assertNotFound();
        $this->get('/programmes/draft-programme')->assertNotFound();
    }

    #[Test]
    public function gsk_058_news_lists_only_published_posts_filters_by_category_and_paginates(): void
    {
        $this->publishedContent('post', 'story-a', 'Story A', 'A text.', ['category' => 'Stories']);
        $this->publishedContent('post', 'update-b', 'Update B', 'B text.', ['category' => 'Updates']);
        $editor = $this->staffWith('PS3', $this->editorKeys());
        $this->workflow()->create($editor, 'post', ['title' => 'Unpublished story', 'slug' => 'unpublished-story'], 'Draft text.');

        $this->get('/news')->assertOk()->assertSee('Story A')->assertSee('Update B')->assertDontSee('Unpublished story');
        $this->get('/news?category=Stories')->assertOk()->assertSee('Story A')->assertDontSee('Update B');
        $this->get('/news?category=' . str_repeat('x', 200))->assertSessionHasErrors('category');

        for ($i = 1; $i <= 11; $i++) {
            $this->publishedContent('post', 'bulk-' . $i, 'Bulk story ' . $i, 'Text ' . $i . '.');
        }
        $this->get('/news?page=2')->assertOk();
    }

    #[Test]
    public function gsk_149_a_draft_title_never_appears_in_public_responses(): void
    {
        $this->publishedContent('page', 'about', 'About us', 'Public text.');
        $this->publishedContent('post', 'story-a', 'Story A', 'Public story.', ['category' => 'Stories']);
        $editor = $this->staffWith('PS4', $this->editorKeys());
        $page = \App\Models\Page::query()->where('slug', 'about')->firstOrFail();
        $post = \App\Models\Post::query()->where('slug', 'story-a')->firstOrFail();
        $this->workflow()->saveDraft($editor, $page, ['title' => 'SECRET DRAFT TITLE'], 'Secret draft body.');
        $this->workflow()->saveDraft($editor, $post, ['title' => 'SECRET DRAFT POST TITLE'], 'Secret draft story.');

        foreach (['/about', '/news', '/news/story-a', '/sitemap.xml', '/programmes', '/'] as $path) {
            $body = $this->get($path)->getContent();
            $this->assertStringNotContainsString('SECRET DRAFT', $body, "A draft title leaked on {$path}.");
            $this->assertStringNotContainsString('Secret draft', $body, "Draft text leaked on {$path}.");
        }
        $this->get('/does-not-exist')->assertNotFound()->assertDontSee('SECRET DRAFT');
    }

    #[Test]
    public function gsk_147_the_sitemap_lists_only_published_public_content(): void
    {
        $this->publishedContent('page', 'about', 'About us');
        $this->publishedContent('post', 'story-a', 'Story A');
        $editor = $this->staffWith('PS5', $this->editorKeys());
        $this->workflow()->create($editor, 'page', ['title' => 'Hidden', 'slug' => 'hidden-page'], 'Hidden text.');

        $xml = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();

        $this->assertStringContainsString('/about</loc>', $xml);
        $this->assertStringContainsString('/news/story-a</loc>', $xml);
        $this->assertStringNotContainsString('hidden-page', $xml);
        $this->assertStringNotContainsString('/staff', $xml);
        $this->assertStringNotContainsString('/account', $xml);
    }

    #[Test]
    public function gsk_147_robots_blocks_private_areas_and_private_pages_are_marked_noindex(): void
    {
        $robots = $this->get('/robots.txt')->assertOk()->getContent();
        foreach (['Disallow: /staff', 'Disallow: /account', 'Disallow: /api/', 'Sitemap: '] as $line) {
            $this->assertStringContainsString($line, $robots);
        }
        $this->get('/login')->assertOk()->assertSee('noindex', false);
        $this->get('/contact')->assertOk()->assertDontSee('noindex', false);
    }

    private function vacancy(string $status = 'published', array $overrides = [], string $verification = 'verified'): Vacancy
    {
        $chain = $this->makeVacancyChain($status);
        $chain['organisation']->forceFill(['verification_status' => $verification, 'verified_at' => $verification === 'verified' ? now() : null])->save();
        $chain['vacancy']->forceFill(array_merge(['title' => 'Role ' . Str::random(5), 'published_at' => now()], $overrides))->save();

        return $chain['vacancy']->refresh();
    }

    #[Test]
    public function gsk_066_public_jobs_show_only_published_open_vacancies_from_verified_employers(): void
    {
        $open = $this->vacancy('published', ['title' => 'Open welder']);
        $this->vacancy('draft', ['title' => 'Draft welder']);
        $this->vacancy('published', ['title' => 'Closed welder', 'closes_at' => now()->subDay()]);
        $this->vacancy('published', ['title' => 'Unverified welder'], 'pending');

        $this->get('/jobs')->assertOk()->assertSee('Open welder')->assertDontSee('Draft welder')->assertDontSee('Closed welder')->assertDontSee('Unverified welder');
        $this->get('/jobs/' . $open->slug)->assertOk()->assertSee('Open welder');
    }

    #[Test]
    public function gsk_066_hidden_vacancies_are_not_reachable_by_guessing_the_address(): void
    {
        $draft = $this->vacancy('draft', ['title' => 'Draft welder']);
        $closed = $this->vacancy('published', ['closes_at' => now()->subHour()]);
        $unverified = $this->vacancy('published', [], 'pending');

        foreach ([$draft, $closed, $unverified] as $v) {
            $this->get('/jobs/' . $v->slug)->assertNotFound();
        }
    }

    #[Test]
    public function gsk_012_the_jobs_search_filters_by_title_and_country_and_validates_input(): void
    {
        $this->vacancy('published', ['title' => 'Care assistant', 'country_code' => 'JP']);
        $this->vacancy('published', ['title' => 'Site welder', 'country_code' => 'KE']);

        $this->get('/jobs?q=welder')->assertOk()->assertSee('Site welder')->assertDontSee('Care assistant');
        $this->get('/jobs?country=jp')->assertOk()->assertSee('Care assistant')->assertDontSee('Site welder');
        $this->get('/jobs?country=KEN')->assertSessionHasErrors('country');
        $this->get('/jobs?q=' . str_repeat('a', 101))->assertSessionHasErrors('q');
        $this->get('/jobs?q=%25')->assertOk();
    }

    #[Test]
    public function the_public_footer_and_navigation_render_without_published_content(): void
    {
        $this->get('/contact')->assertOk()->assertSee('Skip to main content')->assertSee('Safeguarding');
        $this->get('/programmes')->assertOk()->assertSee('No programmes are published yet.');
        $this->get('/news')->assertOk()->assertSee('No news has been published yet.');
        $this->get('/jobs')->assertOk()->assertSee('no open opportunities');
    }
}
