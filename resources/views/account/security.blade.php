@extends('layouts.gsk')
@section('title', 'Security')
@section('content')
<div class="card">
    <h1>Security</h1>
    @if ($mfaConfirmed)
        <p>Two-factor authentication is <strong>on</strong>.@if ($user->isStaff()) Staff cannot turn it off. Ask an administrator if you lose your device.@endif</p>
    @else
        <p>Two-factor authentication is <strong>off</strong>.@if ($user->isStaff()) It is required for staff.@endif</p>
        <div id="mfa-start" @if ($mfaStarted) hidden @endif>
            <button type="button" id="mfa-enable">Turn on two-factor authentication</button>
        </div>
        <div id="mfa-setup" @if (! $mfaStarted) hidden @endif>
            <p>Scan this code with an authenticator app, then enter the 6-digit code it shows.</p>
            <div id="mfa-qr" aria-label="QR code"></div>
            <p>Cannot scan? Enter this key by hand: <code id="mfa-key"></code></p>
            <label for="mfa-code">Code from your app</label>
            <input id="mfa-code" type="text" inputmode="numeric" autocomplete="one-time-code">
            <button type="button" id="mfa-confirm">Confirm</button>
        </div>
        <div id="mfa-codes" hidden>
            <h2>Recovery codes</h2>
            <p>Save these codes somewhere safe. Each works once. They can be shown again only while you are signed in.</p>
            <pre id="mfa-codes-list"></pre>
        </div>
        <p id="mfa-message" role="status"></p>
    @endif
</div>
@unless ($mfaConfirmed)
<script>
(function () {
    var headers = { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}' };
    var msg = document.getElementById('mfa-message');
    var confirmUrl = '{{ route('password.confirm') }}';
    function call(method, url, body) {
        return fetch(url, { method: method, headers: headers, credentials: 'same-origin', body: body ? JSON.stringify(body) : undefined });
    }
    function say(text) { msg.textContent = text; }
    function loadSetup() {
        return call('GET', '/user/two-factor-qr-code').then(function (r) { return r.json(); }).then(function (d) {
            document.getElementById('mfa-qr').innerHTML = d.svg;
            return call('GET', '/user/two-factor-secret-key');
        }).then(function (r) { return r.json(); }).then(function (d) {
            document.getElementById('mfa-key').textContent = d.secretKey;
            document.getElementById('mfa-start').hidden = true;
            document.getElementById('mfa-setup').hidden = false;
        });
    }
    var enable = document.getElementById('mfa-enable');
    if (enable) enable.addEventListener('click', function () {
        call('POST', '/user/two-factor-authentication').then(function (r) {
            if (r.status === 423) { window.location = confirmUrl; return; }
            if (!r.ok) { say('Could not start setup. Please try again.'); return; }
            return loadSetup();
        });
    });
    document.getElementById('mfa-confirm').addEventListener('click', function () {
        var code = document.getElementById('mfa-code').value;
        call('POST', '/user/confirmed-two-factor-authentication', { code: code }).then(function (r) {
            if (r.status === 423) { window.location = confirmUrl; return; }
            if (!r.ok) { say('That code was not accepted. Check the code and try again.'); return; }
            return call('GET', '/user/two-factor-recovery-codes').then(function (x) { return x.json(); }).then(function (codes) {
                document.getElementById('mfa-codes-list').textContent = codes.join('\n');
                document.getElementById('mfa-codes').hidden = false;
                document.getElementById('mfa-setup').hidden = true;
                say('Two-factor authentication is now on.');
            });
        });
    });
    @if ($mfaStarted) loadSetup(); @endif
})();
</script>
@endunless
@endsection
