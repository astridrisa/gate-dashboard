@php
    $total   = (int) $kpi->total;
    $masuk   = (int) $kpi->masuk;
    $keluar  = (int) $kpi->keluar;
    $berarah = $masuk + $keluar;
    $mPct    = $berarah > 0 ? round($masuk / $berarah * 100, 1) : 50;
    $kPct    = round(100 - $mPct, 1);
    $days    = max($dailyTrend->count(), 1);
    $peak    = collect($hourly)->sortByDesc(fn ($h) => $h['masuk'] + $h['keluar'])->first();
    $peakTotal = $peak ? $peak['masuk'] + $peak['keluar'] : 0;
    $exPct   = $total > 0 ? round(((int) $kpi->exceptions) / $total * 100, 1) : 0;
    $deptMax = max((int) ($topDepartments->max('total') ?? 0), 1);
    $hasFilter = request()->filled('date') || request()->filled('device') || request()->filled('department');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Access Get Log</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,100..900&display=swap" rel="stylesheet">
    <style>
        :root {
            --navy: #0b1f3a;
            --navy-2: #142d4f;
            --navy-line: #27456e;
            --paper: #eef2f7;
            --panel: #ffffff;
            --ink: #0f1c2e;
            --muted: #5a6b82;
            --line: #d6dee9;
            --in: #0b8a63;
            --in-soft: #d9f2e9;
            --out: #d9560b;
            --out-soft: #fde6d8;
            --signal: #ffc93c;
            --bad: #b4231a;
            --bad-soft: #fde4e1;
        }
        * { box-sizing: border-box; }
        html { color-scheme: light; }
        body {
            margin: 0; background: var(--paper); color: var(--ink);
            font-family: "Archivo", system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            font-size: 14.5px; line-height: 1.5;
        }
        .num { font-variant-numeric: tabular-nums; }
        :focus-visible { outline: 3px solid var(--signal); outline-offset: 2px; }

        /* ---------- Kerangka: panel kiri + area utama selebar layar ---------- */
        .app { display: grid; grid-template-columns: 300px minmax(0, 1fr); min-height: 100vh; }

        .side {
            background: var(--navy); color: #dbe6f5; padding: 24px 22px 32px;
            position: sticky; top: 0; height: 100vh; overflow-y: auto;
        }
        .brand { display: flex; align-items: center; gap: 12px; margin-bottom: 28px; }
        .brand-mark {
            width: 40px; height: 40px; border-radius: 8px; background: var(--signal);
            display: grid; place-items: center; flex: none;
        }
        .brand-name { font-weight: 800; font-size: 18px; line-height: 1.1; color: #fff; letter-spacing: -.01em; }
        .brand-sub { font-size: 12.5px; color: #9db3d1; }

        .side h2 { font-size: 15px; font-weight: 700; color: #fff; margin: 0 0 12px; }
        .side-block { padding-top: 22px; margin-top: 22px; border-top: 1px solid var(--navy-line); }
        .side label { display: block; font-size: 13px; color: #b7c8e0; margin-bottom: 14px; }
        .side select, .side input[type=password] {
            display: block; width: 100%; margin-top: 5px; padding: 9px 10px; font: inherit; color: #fff;
            background: var(--navy-2); border: 1px solid var(--navy-line); border-radius: 6px;
        }
        .side select option { color: #0f1c2e; background: #fff; }

        .drop {
            display: block; margin: 0 0 14px; padding: 16px 14px; text-align: center; cursor: pointer;
            border: 2px dashed var(--navy-line); border-radius: 8px; color: #dbe6f5;
            transition: border-color .15s, background .15s;
        }
        .drop:hover, .drop.has-file { border-color: var(--signal); background: rgba(255, 201, 60, .07); }
        .drop strong { display: block; color: #fff; font-size: 14px; }
        .drop small { color: #9db3d1; font-size: 12.5px; word-break: break-all; }
        .drop input { position: absolute; width: 1px; height: 1px; opacity: 0; }
        .drop:focus-within { outline: 3px solid var(--signal); outline-offset: 2px; }

        .check { display: flex !important; align-items: center; gap: 8px; }
        .check input { width: 16px; height: 16px; accent-color: var(--signal); }

        .btn {
            display: inline-block; width: 100%; padding: 10px 14px; font: inherit; font-weight: 700;
            text-align: center; text-decoration: none; border-radius: 6px; cursor: pointer; border: 0;
            background: var(--signal); color: var(--navy);
        }
        .btn:hover { background: #ffd65f; }
        .btn.line { background: transparent; color: #dbe6f5; border: 1px solid var(--navy-line); margin-top: 8px; font-weight: 600; }
        .btn.line:hover { border-color: #dbe6f5; background: transparent; }
        .hint { font-size: 12.5px; color: #9db3d1; margin: 12px 0 0; }

        /* ---------- Area utama ---------- */
        .main { padding: 28px clamp(20px, 3vw, 44px) 56px; min-width: 0; }
        .top { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 8px 24px; margin-bottom: 22px; }
        h1 { margin: 0; font-size: clamp(26px, 3vw, 36px); font-weight: 800; letter-spacing: -.02em; line-height: 1.1; }
        .range { color: var(--muted); margin: 4px 0 0; }
        .chips { display: flex; flex-wrap: wrap; gap: 6px; }
        .chip { background: var(--navy); color: #fff; font-size: 13px; padding: 3px 10px; border-radius: 99px; }

        .alert { padding: 11px 16px; border-radius: 6px; margin-bottom: 18px; border-left: 5px solid; background: #fff; }
        .alert.ok { border-color: var(--in); background: var(--in-soft); }
        .alert.err { border-color: var(--bad); background: var(--bad-soft); color: #7a150f; }
        .alert.warn { border-color: var(--signal); background: #fff6d6; color: #5c4400; }

        /* Pita arus: elemen utama halaman */
        .flow { display: flex; border-radius: 8px; overflow: hidden; color: #fff; margin-bottom: 18px; }
        .flow > div { padding: 22px 28px 24px; min-width: 190px; }
        .flow-in  { flex: 1 1 calc(var(--m) * 1%);  background: var(--in);  animation: grow-in .9s cubic-bezier(.2, .7, .2, 1); }
        .flow-out { flex: 1 1 calc(var(--k) * 1%);  background: var(--out); animation: grow-out .9s cubic-bezier(.2, .7, .2, 1); text-align: right; }
        .flow-name { font-size: 17px; font-weight: 700; opacity: .95; }
        .flow-big {
            display: block; font-size: clamp(48px, 8vw, 104px); line-height: .95; font-weight: 800;
            font-stretch: 72%; letter-spacing: -.02em; margin: 6px 0 4px; font-variant-numeric: tabular-nums;
        }
        .flow-note { font-size: 14px; opacity: .92; }
        @keyframes grow-in  { from { flex-basis: 50%; } }
        @keyframes grow-out { from { flex-basis: 50%; } }

        /* Strip ringkasan */
        .strip { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); background: var(--panel); border: 1px solid var(--line); border-radius: 8px; margin-bottom: 18px; }
        .strip > div { padding: 16px 20px; border-right: 1px solid var(--line); }
        .strip > div:last-child { border-right: 0; }
        .strip .k { color: var(--muted); font-size: 13px; }
        .strip .v { font-size: 30px; font-weight: 800; font-stretch: 80%; line-height: 1.15; letter-spacing: -.01em; }
        .strip .s { color: var(--muted); font-size: 13px; }
        .v.bad { color: var(--bad); }

        /* Panel dan grid 12 kolom */
        .grid { display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: 18px; margin-bottom: 18px; }
        .c4 { grid-column: span 4; } .c5 { grid-column: span 5; } .c6 { grid-column: span 6; }
        .c7 { grid-column: span 7; } .c8 { grid-column: span 8; } .c12 { grid-column: span 12; }
        .panel { background: var(--panel); border: 1px solid var(--line); border-radius: 8px; padding: 18px 20px 20px; min-width: 0; }
        .panel h3 { margin: 0; font-size: 16.5px; font-weight: 700; letter-spacing: -.005em; }
        .panel .desc { margin: 2px 0 14px; color: var(--muted); font-size: 13.5px; }
        .chart { position: relative; height: 290px; }

        /* Daftar departemen dengan batang */
        .bars { list-style: none; margin: 0; padding: 0; display: grid; gap: 10px; }
        .bars li { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 2px 12px; align-items: baseline; }
        .bars .nm { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .bars .ct { font-weight: 700; }
        .bars .bar { grid-column: 1 / -1; height: 8px; background: var(--paper); border-radius: 4px; overflow: hidden; }
        .bars .bar i { display: block; height: 100%; background: var(--navy); border-radius: 4px; }

        /* Tabel */
        .scroll { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 9px 10px; border-bottom: 1px solid var(--line); vertical-align: top; }
        th { font-size: 13px; font-weight: 600; color: var(--muted); white-space: nowrap; }
        tbody tr:hover { background: #f6f9fc; }
        td.r, th.r { text-align: right; font-variant-numeric: tabular-nums; }
        .sub { display: block; color: var(--muted); font-size: 12.5px; }
        .pill { display: inline-block; padding: 1px 9px; border-radius: 99px; font-size: 12.5px; font-weight: 600; background: var(--bad-soft); color: var(--bad); }
        .in-t { color: var(--in); font-weight: 700; } .out-t { color: var(--out); font-weight: 700; }
        .muted { color: var(--muted); }

        /* Keadaan kosong */
        .empty { background: var(--panel); border: 2px dashed var(--line); border-radius: 8px; padding: 56px 28px; text-align: center; }
        .empty h2 { margin: 0 0 6px; font-size: 24px; font-weight: 800; letter-spacing: -.01em; }
        .empty p { margin: 0 auto; max-width: 52ch; color: var(--muted); }

        @media (max-width: 1180px) { .c4, .c5, .c6, .c7, .c8 { grid-column: span 12; } }
        @media (max-width: 900px) {
            .app { grid-template-columns: 1fr; }
            .side { position: static; height: auto; }
            .flow { flex-direction: column; }
            .flow > div { flex: none !important; animation: none !important; }
            .flow-out { text-align: left; }
            .strip > div { border-right: 0; border-bottom: 1px solid var(--line); }
        }
        @media (prefers-reduced-motion: reduce) {
            .flow-in, .flow-out { animation: none; }
            * { transition: none !important; }
        }
    </style>
</head>
<body>
<div class="app">

    {{-- ================= Panel kiri ================= --}}
    <aside class="side">
        <div class="brand">
            <div class="brand-mark" aria-hidden="true">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#0b1f3a" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 12h12"/><path d="M11 6l6 6-6 6"/><path d="M20 4v16"/>
                </svg>
            </div>
            <div>
                <div class="brand-name">Access Gate Log</div>
                {{-- <div class="brand-sub">Analisis face recognition dan tap kartu</div> --}}
            </div>
        </div>

        <h2>Filter data</h2>
        <form method="GET" action="{{ route('gate.dashboard') }}">
            <label>Tanggal
                <select name="date">
                    <option value="">Semua tanggal</option>
                    @foreach ($filterDates as $d)
                        <option value="{{ $d }}" @selected(request('date') === $d)>{{ $d }}</option>
                    @endforeach
                </select>
            </label>
            <label>Gate
                <select name="device">
                    <option value="">Semua gate</option>
                    @foreach ($filterDevices as $d)
                        <option value="{{ $d }}" @selected(request('device') === $d)>{{ $d }}</option>
                    @endforeach
                </select>
            </label>
            <label>Departemen
                <select name="department">
                    <option value="">Semua departemen</option>
                    @foreach ($filterDepartments as $d)
                        <option value="{{ $d }}" @selected(request('department') === $d)>{{ $d }}</option>
                    @endforeach
                </select>
            </label>
            <button class="btn" type="submit">Terapkan filter</button>
            @if ($hasFilter)
                <a class="btn line" href="{{ route('gate.dashboard') }}">Hapus filter</a>
            @endif
        </form>

        <div class="side-block">
            <h2>Impor file log</h2>
            <form method="POST" action="{{ route('gate.import') }}" enctype="multipart/form-data">
                @csrf
                <label class="drop" id="drop">
                    <input type="file" name="log_file" id="log_file" accept=".xls,.xlsx,.csv" required>
                    <strong>Pilih file log</strong>
                    <small id="drop-name">Format .xls, .xlsx, atau .csv</small>
                </label>
                <label>Kata sandi import
                    <input type="password" name="import_password" autocomplete="off" required>
                </label>
                <label class="check">
                    <input type="checkbox" name="replace" value="1" checked>
                    Ganti seluruh data lama
                </label>
                <button class="btn" type="submit">Impor dan bersihkan</button>
            </form>
            <p class="hint">Tap ganda dari orang, gate, dan event yang sama dalam kurang dari 5 detik digabung menjadi satu. File besar bisa butuh beberapa menit.</p>
        </div>
    </aside>

    {{-- ================= Area utama ================= --}}
    <main class="main">
        <div class="top">
            <div>
                <h1>Access Gate Analytics Dashboard</h1>
                <p class="range">
                    @if ($filterDates->count() > 0)
                        Data {{ $filterDates->first() }} sampai {{ $filterDates->last() }}, {{ $filterDates->count() }} hari tercatat
                    @else
                        Belum ada data
                    @endif
                </p>
            </div>
            @if ($hasFilter)
                <div class="chips" aria-label="Filter aktif">
                    @if (request()->filled('date'))<span class="chip">{{ request('date') }}</span>@endif
                    @if (request()->filled('device'))<span class="chip">{{ request('device') }}</span>@endif
                    @if (request()->filled('department'))<span class="chip">{{ request('department') }}</span>@endif
                </div>
            @endif
        </div>

        @if (session('success'))
            <div class="alert ok" role="status">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert err" role="alert">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        @if ($total === 0)
            <div class="empty">
                <h2>{{ $hasFilter ? 'Tidak ada data untuk filter ini' : 'Impor file log pertama kamu' }}</h2>
                <p>
                    @if ($hasFilter)
                        Ubah atau hapus filter di panel kiri untuk melihat data lain.
                    @else
                        Pilih file ekspor dari mesin akses di panel kiri, isi kata sandi import, lalu klik Impor dan bersihkan. Dashboard terisi setelah prosesnya selesai.
                    @endif
                </p>
            </div>
        @else
            @if ($lainnya > 0)
                <div class="alert warn">
                    {{ number_format($lainnya) }} tap tidak terdeteksi arahnya karena nama gate tidak memuat kata In/Out atau Masuk/Keluar. Tap ini tidak ikut dihitung di pita Masuk dan Keluar.
                </div>
            @endif

            {{-- Pita arus --}}
            <section class="flow" style="--m: {{ $mPct }}; --k: {{ $kPct }};" aria-label="Perbandingan tap masuk dan keluar">
                <div class="flow-in">
                    <div class="flow-name">Masuk</div>
                    <span class="flow-big">{{ number_format($masuk) }}</span>
                    <div class="flow-note">{{ $mPct }}% dari tap berarah</div>
                </div>
                <div class="flow-out">
                    <div class="flow-name">Keluar</div>
                    <span class="flow-big">{{ number_format($keluar) }}</span>
                    <div class="flow-note">{{ $kPct }}% dari tap berarah</div>
                </div>
            </section>

            {{-- Ringkasan --}}
            <section class="strip">
                <div>
                    <div class="k">Total tap bersih</div>
                    <div class="v num">{{ number_format($total) }}</div>
                    <div class="s num">rata-rata {{ number_format(round($total / $days)) }} per hari</div>
                </div>
                <div>
                    <div class="k">Personel unik</div>
                    <div class="v num">{{ number_format($kpi->unique_personnel) }}</div>
                    <div class="s">yang tercatat tap</div>
                </div>
                <div>
                    <div class="k">Jam tersibuk</div>
                    <div class="v num">{{ $peakTotal > 0 ? $peak['hour'] . ':00' : '-' }}</div>
                    <div class="s num">{{ number_format($peakTotal) }} tap pada jam itu</div>
                </div>
                <div>
                    <div class="k">Eksepsi</div>
                    <div class="v num bad">{{ number_format($kpi->exceptions) }}</div>
                    <div class="s num">{{ $exPct }}% dari total tap</div>
                </div>
            </section>

            {{-- Tren --}}
            <section class="grid">
                <div class="panel c7">
                    <h3>Tren harian</h3>
                    <p class="desc">Jumlah tap masuk dan keluar tiap hari.</p>
                    <div class="chart"><canvas id="dailyChart"></canvas></div>
                </div>
                <div class="panel c5">
                    <h3>Distribusi per jam</h3>
                    <p class="desc">Jam berapa gate paling ramai dalam sehari.</p>
                    <div class="chart"><canvas id="hourlyChart"></canvas></div>
                </div>
            </section>

            <section class="grid">
                <div class="panel c6">
                    <h3>Beban per gate</h3>
                    <p class="desc">Jumlah tap di setiap gate.</p>
                    <div class="chart" style="height: {{ max(220, $gateBreakdown->count() * 38 + 40) }}px"><canvas id="gateChart"></canvas></div>
                </div>
                <div class="panel c6">
                    <h3>10 departemen teraktif</h3>
                    <p class="desc">Departemen dengan tap terbanyak.</p>
                    <ul class="bars">
                        @foreach ($topDepartments as $d)
                            <li>
                                <span class="nm" title="{{ $d->department_name }}">{{ $d->department_name }}</span>
                                <span class="ct num">{{ number_format($d->total) }}</span>
                                <span class="bar"><i style="width: {{ round($d->total / $deptMax * 100, 1) }}%"></i></span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>

            {{-- Tabel --}}
            <section class="grid">
                <div class="panel c12">
                    <h3>15 personel teraktif</h3>
                    <p class="desc">Urut dari tap terbanyak.</p>
                    <div class="scroll">
                        <table>
                            <thead><tr>
                                <th>Nama</th><th>Departemen</th>
                                <th class="r">Total</th><th class="r">Masuk</th><th class="r">Keluar</th><th class="r">Hari aktif</th>
                            </tr></thead>
                            <tbody>
                            @forelse ($topPersonnel as $p)
                                <tr>
                                    <td>{{ $p->full_name }}<span class="sub num">ID {{ $p->personnel_id }}</span></td>
                                    <td>{{ $p->department_name }}</td>
                                    <td class="r"><strong>{{ number_format($p->total) }}</strong></td>
                                    <td class="r in-t">{{ number_format($p->masuk) }}</td>
                                    <td class="r out-t">{{ number_format($p->keluar) }}</td>
                                    <td class="r">{{ number_format($p->active_days) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="muted">Belum ada tap dengan ID personel.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section class="grid">
                <div class="panel c12">
                    <h3>Eksepsi terbaru</h3>
                    <p class="desc">50 tap terakhir yang tidak berstatus {{ \App\Support\GateLogProcessor::NORMAL_EVENT }}, misalnya kartu kedaluwarsa atau tidak terdaftar.</p>
                    <div class="scroll">
                        <table>
                            <thead><tr>
                                <th>Waktu</th><th>Gate</th><th>Event</th><th>Nama</th><th>Departemen</th>
                            </tr></thead>
                            <tbody>
                            @forelse ($exceptions as $e)
                                <tr>
                                    <td class="num" style="white-space:nowrap">{{ $e->tap_time->format('Y-m-d H:i:s') }}</td>
                                    <td>{{ $e->device_name }}</td>
                                    <td><span class="pill">{{ $e->event_description }}</span>@if ($e->event_level)<span class="sub">{{ $e->event_level }}</span>@endif</td>
                                    <td>{{ $e->full_name }}</td>
                                    <td>{{ $e->department_name }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="muted">Tidak ada eksepsi pada data ini.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        @endif
    </main>
</div>

<script>
    // Tampilkan nama file yang dipilih di kotak impor
    (function () {
        var input = document.getElementById('log_file');
        var name = document.getElementById('drop-name');
        var drop = document.getElementById('drop');
        if (!input) return;
        input.addEventListener('change', function () {
            var f = input.files && input.files[0];
            name.textContent = f ? f.name : 'Format .xls, .xlsx, atau .csv';
            drop.classList.toggle('has-file', !!f);
        });
    })();
</script>

@if ($total > 0)
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.js"></script>
<script>
    var C = { masuk: '#0b8a63', keluar: '#d9560b', navy: '#142d4f', grid: '#e3e9f1', text: '#5a6b82' };
    var daily  = @json($dailyTrend->map(fn ($r) => ['d' => $r->tap_date, 'm' => (int) $r->masuk, 'k' => (int) $r->keluar])->values());
    var hourly = @json($hourly);
    var gates  = @json($gateBreakdown->map(fn ($r) => ['n' => $r->device_name, 't' => (int) $r->total])->values());

    Chart.defaults.font.family = '"Archivo", system-ui, sans-serif';
    Chart.defaults.font.size = 12.5;
    Chart.defaults.color = C.text;
    Chart.defaults.animation = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? false : { duration: 500 };

    var legend = { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 16 } };
    var yAxis = { beginAtZero: true, grid: { color: C.grid }, border: { display: false }, ticks: { precision: 0 } };
    var xAxis = { grid: { display: false } };

    new Chart(document.getElementById('dailyChart'), {
        type: daily.length > 14 ? 'line' : 'bar',
        data: {
            labels: daily.map(function (r) { return r.d; }),
            datasets: [
                { label: 'Masuk',  data: daily.map(function (r) { return r.m; }), backgroundColor: C.masuk,  borderColor: C.masuk,  borderRadius: 3, tension: .25 },
                { label: 'Keluar', data: daily.map(function (r) { return r.k; }), backgroundColor: C.keluar, borderColor: C.keluar, borderRadius: 3, tension: .25 }
            ]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: legend }, scales: { x: xAxis, y: yAxis } }
    });

    new Chart(document.getElementById('hourlyChart'), {
        type: 'bar',
        data: {
            labels: hourly.map(function (r) { return r.hour; }),
            datasets: [
                { label: 'Masuk',  data: hourly.map(function (r) { return r.masuk; }),  backgroundColor: C.masuk,  borderRadius: 2 },
                { label: 'Keluar', data: hourly.map(function (r) { return r.keluar; }), backgroundColor: C.keluar, borderRadius: 2 }
            ]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: legend }, scales: { x: { grid: { display: false }, ticks: { maxRotation: 0 } }, y: yAxis } }
    });

    new Chart(document.getElementById('gateChart'), {
        type: 'bar',
        data: {
            labels: gates.map(function (r) { return r.n; }),
            datasets: [{ label: 'Jumlah tap', data: gates.map(function (r) { return r.t; }), backgroundColor: C.navy, borderRadius: 3, barThickness: 20 }]
        },
        options: {
            indexAxis: 'y', responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, grid: { color: C.grid }, border: { display: false }, ticks: { precision: 0 } }, y: { grid: { display: false } } }
        }
    });
</script>
@endif
</body>
</html>