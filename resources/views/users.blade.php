@php
    $roleLabels = \App\Support\GateRole::LABELS;   // [1 => Super Admin, 2 => Admin, 3 => Viewer]
    $roleNotes  = [
        1 => 'Akses penuh dan bisa membuat akun',
        2 => 'Akses penuh data, tanpa kelola akun',
        3 => 'Hanya melihat dashboard',
    ];
    $counts = $users->countBy(fn ($u) => (int) $u->role_id);
    $initials = fn ($name) => mb_strtoupper(collect(preg_split('/\s+/', trim((string) $name)))->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode(''));
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kelola Akun | Access Gate Log</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon-kemenkeu.png') }}?v=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/gate-auth.css') }}">
</head>
<body class="app-page">

    <header class="u-bar">
        <div class="u-bar-brand">
            <span class="auth-mark" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#0b1f3a" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 12h12"/><path d="M11 6l6 6-6 6"/><path d="M20 4v16"/>
                </svg>
            </span>
            <div>
                <div class="u-bar-title">Kelola akun</div>
                <div class="u-bar-sub">Masuk sebagai {{ auth()->user()->name }}</div>
            </div>
        </div>
        <div class="u-bar-actions">
            <a class="a-btn sm" href="{{ route('gate.dashboard') }}">Kembali ke dashboard</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="a-btn sm outline-light" type="submit">Keluar</button>
            </form>
        </div>
    </header>

    <div class="u-page">

        @if (session('success'))
            <div class="a-alert ok" role="status">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M8 12.5l3 3 5-6"/></svg>
                <div>{{ session('success') }}</div>
            </div>
        @endif
        @if ($errors->any())
            <div class="a-alert err" role="alert">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5"/><path d="M12 16.5v.01"/></svg>
                <div>@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
            </div>
        @endif

        {{-- Ringkasan peran --}}
        <section class="u-stats" aria-label="Jumlah akun per peran">
            @foreach ($roleLabels as $id => $label)
                <div class="u-stat">
                    <div class="u-stat-top">
                        <span class="role role-{{ \App\Support\GateRole::slug($id) }}">{{ $label }}</span>
                    </div>
                    <div class="u-stat-n">{{ $counts[$id] ?? 0 }}</div>
                    <div class="u-stat-note">{{ $roleNotes[$id] }}</div>
                </div>
            @endforeach
        </section>

        <div class="u-grid">

            {{-- Form tambah akun --}}
            <section class="u-new">
                <h2>Tambah akun</h2>
                <p class="u-muted">Akun baru langsung bisa masuk dengan email dan kata sandi ini.</p>
                <form method="POST" action="{{ route('users.store') }}">
                    @csrf
                    <label class="f">
                        <span class="f-label">Nama</span>
                        <input type="text" name="name" value="{{ old('name') }}" required maxlength="100" autocomplete="off">
                    </label>
                    <label class="f">
                        <span class="f-label">Email</span>
                        <input type="email" name="email" value="{{ old('email') }}" required autocomplete="off">
                    </label>
                    <label class="f">
                        <span class="f-label">Peran</span>
                        <select name="role_id" required>
                            @foreach ($roleLabels as $val => $label)
                                <option value="{{ $val }}" @selected((int) old('role_id', 3) === $val)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="f">
                        <span class="f-label">Kata sandi (minimal 8 karakter)</span>
                        <input type="password" name="password" required minlength="8" autocomplete="new-password">
                    </label>
                    <label class="f">
                        <span class="f-label">Ulangi kata sandi</span>
                        <input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
                    </label>
                    <button class="a-btn block" type="submit">Buat akun</button>
                </form>
            </section>

            {{-- Daftar akun --}}
            <section>
                <div class="u-list-head">
                    <h2>Daftar akun <span class="u-count">{{ $users->count() }}</span></h2>
                    <input type="search" id="userSearch" class="u-search" placeholder="Cari nama atau email" aria-label="Cari akun" autocomplete="off">
                </div>

                <div class="u-rows" id="userRows">
                    @foreach ($users as $u)
                        @php $slug = \App\Support\GateRole::slug($u->role_id); @endphp
                        <article class="u-row" data-search="{{ mb_strtolower($u->name . ' ' . $u->email) }}">
                            <div class="u-who">
                                <span class="u-avatar r{{ (int) $u->role_id }}" aria-hidden="true">{{ $initials($u->name) }}</span>
                                <div class="u-who-text">
                                    <div class="u-name">
                                        {{ $u->name }}
                                        @if ($u->is(auth()->user()))<span class="u-self">akun kamu</span>@endif
                                    </div>
                                    <div class="u-mail" title="{{ $u->email }}">{{ $u->email }}</div>
                                </div>
                                <span class="role role-{{ $slug }}">{{ $roleLabels[(int) $u->role_id] ?? '-' }}</span>
                            </div>

                            <div class="u-tools">
                                <form method="POST" action="{{ route('users.role', $u) }}">
                                    @csrf @method('PATCH')
                                    <select name="role_id" aria-label="Peran {{ $u->email }}">
                                        @foreach ($roleLabels as $val => $label)
                                            <option value="{{ $val }}" @selected((int) $u->role_id === $val)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <button class="a-btn sm navy" type="submit">Simpan</button>
                                </form>

                                <details class="u-edit">
                                    <summary class="a-btn sm ghost">Edit data</summary>
                                    <form method="POST" action="{{ route('users.update', $u) }}">
                                        @csrf @method('PATCH')
                                        <input type="text" name="name" value="{{ $u->name }}" required maxlength="100" aria-label="Nama {{ $u->email }}">
                                        <input type="email" name="email" value="{{ $u->email }}" required aria-label="Email {{ $u->email }}">
                                        <button class="a-btn sm navy" type="submit">Simpan</button>
                                    </form>
                                </details>

                                <details class="u-reset">
                                    <summary class="a-btn sm ghost">Reset sandi</summary>
                                    <form method="POST" action="{{ route('users.password', $u) }}">
                                        @csrf @method('PATCH')
                                        <input type="password" name="password" placeholder="Sandi baru (min 8)" minlength="8" required autocomplete="new-password" aria-label="Sandi baru {{ $u->email }}">
                                        <button class="a-btn sm navy" type="submit">Reset</button>
                                    </form>
                                </details>

                                @unless ($u->is(auth()->user()))
                                    <form method="POST" action="{{ route('users.destroy', $u) }}" onsubmit="return confirm('Hapus akun {{ $u->email }}?')">
                                        @csrf @method('DELETE')
                                        <button class="a-btn sm danger-outline" type="submit">Hapus</button>
                                    </form>
                                @endunless
                            </div>
                        </article>
                    @endforeach
                    <p class="u-empty" id="userEmpty" hidden>Tidak ada akun yang cocok dengan pencarianmu.</p>
                </div>
            </section>
        </div>
    </div>

<script>
    // Cari akun di sisi browser
    (function () {
        var input = document.getElementById('userSearch');
        var rows = document.querySelectorAll('.u-row');
        var empty = document.getElementById('userEmpty');
        input.addEventListener('input', function () {
            var q = input.value.trim().toLowerCase(), shown = 0;
            rows.forEach(function (r) {
                var ok = !q || r.dataset.search.indexOf(q) !== -1;
                r.hidden = !ok;
                if (ok) shown++;
            });
            empty.hidden = shown !== 0;
        });
    })();

    // Hanya satu panel (Edit data / Reset sandi) terbuka per akun
    (function () {
        document.querySelectorAll('.u-tools details').forEach(function (d) {
            d.addEventListener('toggle', function () {
                if (!d.open) return;
                d.parentElement.querySelectorAll('details').forEach(function (o) {
                    if (o !== d) o.open = false;
                });
            });
        });
    })();
</script>
</body>
</html>