<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ContentVersion;
use App\Models\Page;
use App\Models\Post;
use App\Models\Vacancy;
use App\Support\Content\BlockMedia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

// Public pages. Everything here reads only PUBLISHED versions and approved, open vacancies.
// A draft title or body must never reach any response from this controller.
class PublicController extends Controller
{
    public function home()
    {
        $page = Page::published()->with('publishedVersion')->where('slug', 'home')->first();
        if ($page === null) {
            return view('public.empty');
        }

        return $this->render($page->publishedVersion);
    }

    public function page(string $slug)
    {
        abort_if(in_array($slug, (array) config('gsk.content.reserved_slugs'), true), 404);
        $page = Page::published()->with('publishedVersion')->where('slug', $slug)->whereIn('kind', ['page', 'policy'])->firstOrFail();

        return $this->render($page->publishedVersion);
    }

    public function programmes()
    {
        $items = Page::published()->with('publishedVersion')->where('kind', 'programme')->orderByDesc('published_at')->paginate(12)
            ->through(fn (Page $p) => ['title' => $p->publishedVersion->title, 'summary' => $p->publishedVersion->summary, 'url' => route('programmes.show', $p->slug), 'date' => null]);

        return view('public.list', ['heading' => 'Programmes', 'items' => $items, 'empty' => 'No programmes are published yet.', 'categories' => collect(), 'category' => null]);
    }

    public function programme(string $slug)
    {
        $page = Page::published()->with('publishedVersion')->where('slug', $slug)->where('kind', 'programme')->firstOrFail();

        return $this->render($page->publishedVersion);
    }

    public function news(Request $request)
    {
        $data = $request->validate(['category' => ['nullable', 'string', 'max:100']]);
        $category = $data['category'] ?? null;

        $query = Post::published()->with('publishedVersion')->orderByDesc('published_at');
        if ($category !== null && $category !== '') {
            $query->where('category', $category);
        }
        $items = $query->paginate(10)->withQueryString()
            ->through(fn (Post $p) => ['title' => $p->publishedVersion->title, 'summary' => $p->publishedVersion->summary, 'url' => route('news.show', $p->slug), 'date' => $p->published_at]);
        $categories = Post::published()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category');

        return view('public.list', ['heading' => 'News and stories', 'items' => $items, 'empty' => 'No news has been published yet.', 'categories' => $categories, 'category' => $category]);
    }

    public function newsPost(string $slug)
    {
        $post = Post::published()->with('publishedVersion')->where('slug', $slug)->firstOrFail();

        return $this->render($post->publishedVersion, $post->published_at);
    }

    public function jobs(Request $request)
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'country' => ['nullable', 'alpha', 'size:2']]);
        $query = $this->openVacancies()->with('organisation')->orderByDesc('published_at');
        if (! empty($data['q'])) {
            $query->where('title', 'like', '%' . addcslashes($data['q'], '%_\\') . '%');
        }
        if (! empty($data['country'])) {
            $query->where('country_code', strtoupper($data['country']));
        }

        return view('public.jobs', ['vacancies' => $query->paginate(10)->withQueryString(), 'q' => $data['q'] ?? '', 'country' => $data['country'] ?? '']);
    }

    public function job(string $slug)
    {
        $vacancy = $this->openVacancies()->with('organisation')->where('slug', $slug)->firstOrFail();

        return view('public.job', ['vacancy' => $vacancy]);
    }

    public function sitemap()
    {
        $urls = [url('/'), route('programmes'), route('news'), route('jobs'), route('contact')];
        foreach (Page::published()->whereIn('kind', ['page', 'policy'])->where('slug', '!=', 'home')->pluck('slug') as $slug) {
            $urls[] = route('page', $slug);
        }
        foreach (Page::published()->where('kind', 'programme')->pluck('slug') as $slug) {
            $urls[] = route('programmes.show', $slug);
        }
        foreach (Post::published()->pluck('slug') as $slug) {
            $urls[] = route('news.show', $slug);
        }
        foreach ($this->openVacancies()->pluck('slug') as $slug) {
            $urls[] = route('jobs.show', $slug);
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach (array_unique($urls) as $u) {
            $xml .= '  <url><loc>' . htmlspecialchars($u, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc></url>' . "\n";
        }

        return response($xml . '</urlset>' . "\n", 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots()
    {
        $lines = ['User-agent: *', 'Disallow: /staff', 'Disallow: /account', 'Disallow: /login', 'Disallow: /forgot-password',
            'Disallow: /reset-password', 'Disallow: /two-factor-challenge', 'Disallow: /user/', 'Disallow: /api/', 'Allow: /',
            'Sitemap: ' . url('/sitemap.xml')];

        return response(implode("\n", $lines) . "\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    private function openVacancies(): Builder
    {
        return Vacancy::query()->publiclyVisible()->where(function (Builder $q) {
            $q->whereNull('closes_at')->orWhere('closes_at', '>', now());
        });
    }

    private function render(ContentVersion $version, $date = null)
    {
        return view('public.page', [
            'version' => $version,
            'media' => BlockMedia::for($version->blocks ?? []),
            'date' => $date,
        ]);
    }
}
