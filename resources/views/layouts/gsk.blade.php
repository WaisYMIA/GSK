<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Global Skills Kenya')</title>
    <meta name="robots" content="noindex, nofollow">
    <style>
        :root { --navy:#102F50; --green:#168451; --bg:#F5F7FA; --ink:#1b2430; }
        body { font-family: Inter, "Source Sans 3", system-ui, sans-serif; margin:0; background:var(--bg); color:var(--ink); line-height:1.6; font-size:17px; }
        a { color:var(--navy); }
        .skip { position:absolute; left:-999px; } .skip:focus { left:8px; top:8px; background:#fff; padding:8px; z-index:10; }
        header { background:var(--navy); color:#fff; padding:12px 20px; } header a { color:#fff; }
        main { max-width:640px; margin:24px auto; padding:0 16px; }
        .card { background:#fff; border:1px solid #d7dde5; border-radius:8px; padding:20px; }
        label { display:block; font-weight:600; margin:14px 0 4px; }
        input[type=text], input[type=email], input[type=password] { width:100%; box-sizing:border-box; padding:10px; font-size:1rem; border:1px solid #6b7785; border-radius:6px; }
        button { background:var(--green); color:#fff; border:0; border-radius:6px; padding:10px 18px; font-size:1rem; margin-top:16px; cursor:pointer; }
        :focus-visible { outline:3px solid #E7B75B; outline-offset:2px; }
        .alert { border:2px solid #b3261e; background:#fdecea; padding:12px; border-radius:6px; margin-bottom:12px; }
        .status { border:2px solid var(--green); background:#e8f5ee; padding:12px; border-radius:6px; margin-bottom:12px; }
        code { background:#eef1f5; padding:2px 6px; border-radius:4px; }
    </style>
</head>
<body>
<a class="skip" href="#main">Skip to main content</a>
<header><a href="{{ url('/') }}"><strong>Global Skills Kenya</strong></a></header>
<main id="main" tabindex="-1">
    @if (session('status'))<div class="status" role="status">{{ session('status') }}</div>@endif
    @if ($errors->any())
        <div class="alert" role="alert" id="error-summary">
            <strong>There is a problem</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    @yield('content')
</main>
</body>
</html>
