<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk | Access Gate Log</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon-kemenkeu.png') }}?v=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/gate-auth.css') }}">
</head>
<body class="auth">
<div class="auth-shell">

    {{-- ============ Sisi kiri: identitas ============ --}}
    <aside class="auth-hero">
        <div class="auth-brand">
            <span class="auth-mark" aria-hidden="true">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#0b1f3a" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 12h12"/><path d="M11 6l6 6-6 6"/><path d="M20 4v16"/>
                </svg>
            </span>
            <span class="auth-brand-name">Access Gate Log</span>
        </div>

        <div class="auth-hero-body">
            <h2 class="auth-headline">Masuk.<br>Keluar.<br>Tercatat.</h2>
            <p class="auth-lead">Analitik tap face recognition dan kartu akses di gate lobi, dalam satu dashboard.</p>

            <div class="ribbon" aria-hidden="true">
                <div class="ribbon-in">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12h14"/><path d="M13 6l6 6-6 6"/></svg>
                    <span>Masuk</span>
                </div>
                <div class="ribbon-out">
                    <span>Keluar</span>
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 12H6"/><path d="M11 6l-6 6 6 6"/></svg>
                </div>
            </div>
        </div>
    </aside>

    {{-- ============ Sisi kanan: form ============ --}}
    <main class="auth-main">
        <div class="auth-form">
            <h1>Masuk ke dashboard</h1>
            <p class="auth-sub">Gunakan email dan kata sandi dari administrator.</p>

            @if ($errors->any())
                <div class="a-alert err" role="alert">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5"/><path d="M12 16.5v.01"/></svg>
                    <div>@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
                </div>
            @endif

            <form method="POST" action="{{ route('login.attempt') }}" id="loginForm">
                @csrf
                <label class="f">
                    <span class="f-label">Email</span>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="nama@kemenkeu.go.id">
                </label>

                <label class="f">
                    <span class="f-label">Kata sandi</span>
                    <span class="f-pass">
                        <input type="password" name="password" id="password" required autocomplete="current-password">
                        <button type="button" class="f-toggle" id="togglePass" aria-pressed="false" aria-controls="password">Tampilkan</button>
                    </span>
                    <span class="f-hint" id="capsHint" role="status">Caps Lock sedang aktif.</span>
                </label>

                <label class="f-check">
                    <input type="checkbox" name="remember" value="1">
                    <span>Tetap masuk di perangkat ini</span>
                </label>

                <button class="a-btn block" type="submit" id="btnLogin">Masuk</button>
            </form>

            <p class="auth-help">Belum punya akun atau lupa kata sandi? Hubungi Super Admin.</p>
        </div>
    </main>
</div>

<script>
    (function () {
        var pass = document.getElementById('password');
        var toggle = document.getElementById('togglePass');
        var caps = document.getElementById('capsHint');
        var form = document.getElementById('loginForm');
        var btn = document.getElementById('btnLogin');

        // Tampilkan / sembunyikan kata sandi
        toggle.addEventListener('click', function () {
            var show = pass.type === 'password';
            pass.type = show ? 'text' : 'password';
            toggle.textContent = show ? 'Sembunyikan' : 'Tampilkan';
            toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
            pass.focus();
        });

        // Peringatan Caps Lock
        function checkCaps(e) {
            if (!e.getModifierState) return;
            caps.classList.toggle('on', e.getModifierState('CapsLock'));
        }
        pass.addEventListener('keydown', checkCaps);
        pass.addEventListener('keyup', checkCaps);
        pass.addEventListener('blur', function () { caps.classList.remove('on'); });

        // Cegah klik ganda saat memproses
        form.addEventListener('submit', function () {
            if (!form.checkValidity()) return;
            btn.disabled = true;
            btn.textContent = 'Memeriksa...';
        });
    })();
</script>
</body>
</html>