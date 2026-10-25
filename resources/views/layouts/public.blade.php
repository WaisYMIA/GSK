<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Global Skills Kenya')</title>
    @hasSection('description')<meta name="description" content="@yield('description')">@endif
    <link rel="canonical" href="{{ url()->current() }}">
    <style>
        :root { --navy:#102F50; --green:#168451; --bg:#F5F7FA; --ink:#1b2430; }
        body { font-family: Inter, "Source Sans 3", system-ui, sans-serif; margin:0; background:#fff; color:var(--ink); line-height:1.7; font-size:18px; }
        a { color:var(--navy); }
        .skip { position:absolute; left:-999px; } .skip:focus { left:8px; top:8px; background:#fff; padding:8px; z-index:10; }
        header.site { background:var(--navy); color:#fff; } header.site .in { max-width:960px; margin:0 auto; padding:12px 16px; display:flex; flex-wrap:wrap; gap:16px; align-items:center; }
        header.site a { color:#fff; text-decoration:none; font-weight:600; } header.site nav { display:flex; flex-wrap:wrap; gap:14px; }
        main { max-width:760px; margin:24px auto; padding:0 16px; }
        footer.site { background:var(--bg); padding:20px 16px; margin-top:40px; font-size:16px; } footer.site .in { max-width:960px; margin:0 auto; }
        :focus-visible { outline:3px solid #E7B75B; outline-offset:2px; }
        figure { margin:20px 0; } figure img { max-width:100%; height:auto; } figcaption { font-size:15px; color:#44505e; }
        .img-left { float:left; max-width:45%; margin:6px 20px 10px 0; } .img-right { float:right; max-width:45%; margin:6px 0 10px 20px; } .img-center { text-align:center; }
        @media (max-width:600px) { .img-left, .img-right { float:none; max-width:100%; margin:16px 0; } }
        blockquote { border-left:4px solid var(--green); margin:20px 0; padding:4px 16px; color:#33404d; }
        .callout { background:var(--bg); border:1px solid #d7dde5; border-radius:8px; padding:12px 16px; margin:20px 0; }
        .card { border:1px solid #d7dde5; border-radius:8px; padding:14px 16px; margin:12px 0; } .meta { color:#44505e; font-size:15px; }
        label { display:block; font-weight:600; margin:14px 0 4px; }
        input[type=text], input[type=email], textarea, select { width:100%; box-sizing:border-box; padding:10px; font-size:1rem; border:1px solid #6b7785; border-radius:6px; }
        button { background:var(--green); color:#fff; border:0; border-radius:6px; padding:10px 18px; font-size:1rem; margin-top:16px; cursor:pointer; }
        .alert { border:2px solid #b3261e; background:#fdecea; padding:12px; border-radius:6px; margin-bottom:12px; }
        .status { border:2px solid var(--green); background:#e8f5ee; padding:12px; border-radius:6px; margin-bottom:12px; }
        .hp { position:absolute; left:-9999px; }
    </style>
</head>
<body>
<a class="skip" href="#main">Skip to main content</a>
<header class="site"><div class="in">
    <a href="{{ url('/') }}">Global Skills Kenya</a>
    <nav aria-label="Main">
        <a href="{{ route('programmes') }}">Programmes</a>
        <a href="{{ route('jobs') }}">Opportunities</a>
        <a href="{{ route('news') }}">News</a>
        <a href="{{ route('contact') }}">Contact</a>
        <a href="{{ route('login') }}">Sign in</a>
    </nav>
</div></header>
<main id="main" tabindex="-1">
    @if (session('status'))<div class="status" role="status">{{ session('status') }}</div>@endif
    @if ($errors->any())
        <div class="alert" role="alert" id="error-summary"><strong>There is a problem</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @yield('content')
</main>
<footer class="site"><div class="in">
    <nav aria-label="Footer">
        <a href="{{ route('page', 'safeguarding') }}">Safeguarding</a> &middot;
        <a href="{{ route('page', 'privacy') }}">Privacy</a> &middot;
        <a href="{{ route('page', 'terms') }}">Terms</a> &middot;
        <a href="{{ route('page', 'accessibility') }}">Accessibility</a>
    </nav>
    <p>Global Skills Kenya</p>
</div></footer>
</body>
</html>
