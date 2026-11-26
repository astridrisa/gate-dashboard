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
    <link rel="icon" type="image/png" href="{{ asset('favicon-kemenkeu.png') }}?v=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/gate-dashboard.css') }}">
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

        @if (\Illuminate\Support\Facades\Route::has('gate.clear'))
        <div class="side-block">
            <h2>Kosongkan data</h2>
            <form method="POST" action="{{ route('gate.clear') }}" onsubmit="return confirm('Hapus SEMUA data log? Tindakan ini tidak bisa dibatalkan.')">
                @csrf
                <label>Kata sandi clear
                    <input type="password" name="clear_password" autocomplete="off" required>
                </label>
                <button class="btn danger" type="submit">Clear semua data</button>
            </form>
            <p class="hint">Menghapus seluruh data di dashboard. Setelah itu kamu bisa upload dari awal lagi.</p>
        </div>
        @endif
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
                {{-- <div>
                    <div class="k">Tap ganda</div>
                    <div class="v num bad">{{ $tapGanda !== null ? number_format($tapGanda) : '-' }}</div>
                    <div class="s num">
                        @if ($tapGanda === null)
                            kolom jumlah tap tidak ditemukan
                        @else
                            {{ $tapMentah > 0 ? round($tapGanda / $tapMentah * 100, 1) : 0 }}% dari {{ number_format($tapMentah) }} tap mentah
                        @endif
                    </div>
                </div> --}}
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

            {{-- ======== Bagian tambahan (analisa gate, verifikasi, tap out terakhir, eksepsi, riwayat) ======== --}}
            @php
    $keep = request()->except(['jam', 'out_page', 'eks', 'q', 'log_page']);
    $vTotal = max((int) $verifikasi->sum(), 1);
    $vFace  = round(($verifikasi['Face'] ?? 0) / $vTotal * 100, 2);
    $vCard  = round(($verifikasi['Only Card'] ?? 0) / $vTotal * 100, 2);
    $vOther = max(round(100 - $vFace - $vCard, 2), 0);
    $inCards  = collect($gateCards)->where('arah', 'in')->sortByDesc('total')->values();
    $outCards = collect($gateCards)->where('arah', 'out')->sortByDesc('total')->values();
    $fmtJam = fn ($h) => sprintf('%02d:00 - %02d:59 WIB', $h, $h);
@endphp



{{-- ============ 1. Analisa singkat trafik & jam puncak tiap gate ============ --}}
<section class="grid">
    <div class="panel c12">
        <div class="panel-head">
            <div>
                <h3>Analisa Singkat Trafik &amp; Jam Puncak Setiap Gate <span class="badge-n">{{ count($gateCards) }} Gate</span></h3>
                <p class="desc">Ringkasan waktu tap terbanyak (peak hour) untuk gate In maupun Out pada setiap perangkat.</p>
            </div>
            <div class="toolbar">
                <select id="fArah" aria-label="Filter arah">
                    <option value="">Semua Arah (Gate In &amp; Gate Out)</option>
                    <option value="in">Gate In saja</option>
                    <option value="out">Gate Out saja</option>
                </select>
                <select id="fGate" aria-label="Filter unit gate">
                    <option value="">Semua Unit Gate ({{ count($gateCards) }})</option>
                    @foreach ($gateCards as $g)<option value="{{ $g['name'] }}">{{ $g['name'] }}</option>@endforeach
                </select>
            </div>
        </div>

        <div class="gcards">
            @foreach ($gateCards as $g)
                <div class="gcard {{ $g['arah'] }}" data-arah="{{ $g['arah'] }}" data-gate="{{ $g['name'] }}">
                    <div class="gcard-head">
                        <strong>{{ $g['name'] }}</strong>
                        <span class="tag">{{ $g['arah'] === 'in' ? 'Gate Masuk (In)' : 'Gate Keluar (Out)' }}</span>
                    </div>
                    <div class="peak">
                        <div class="l">Waktu Tap Terbanyak (Peak Hour)</div>
                        <div class="row">
                            <span class="big num">{{ $fmtJam($g['peak']['h']) }}</span>
                            <span class="num">{{ number_format($g['peak']['c']) }} tap ({{ $g['peak']['pct'] }}%)</span>
                        </div>
                        @if ($g['peak2'])
                            <div class="l num">Puncak ke-2: <strong>{{ $fmtJam($g['peak2']['h']) }}</strong> ({{ number_format($g['peak2']['c']) }} tap)</div>
                        @endif
                    </div>
                    <div class="foot num">
                        <span>Total Beban: <strong>{{ number_format($g['total']) }}</strong> tx ({{ $g['share'] }}%)</span>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="sum-box">
            <strong>Ringkasan Analisa Trafik Gate:</strong>
            <ul class="num">
                @if ($inCards->count())
                    <li><strong>Trafik Gate Masuk (In):</strong> jalur masuk terpadat adalah <strong>{{ $inCards[0]['name'] }}</strong>
                        ({{ number_format($inCards[0]['total']) }} transaksi, puncak pada {{ $fmtJam($inCards[0]['peak']['h']) }} sebanyak {{ number_format($inCards[0]['peak']['c']) }} tap)
                        @if ($inCards->count() > 1), diikuti <strong>{{ $inCards[1]['name'] }}</strong> ({{ number_format($inCards[1]['total']) }} transaksi, puncak pada {{ $fmtJam($inCards[1]['peak']['h']) }} sebanyak {{ number_format($inCards[1]['peak']['c']) }} tap)@endif.
                    </li>
                @endif
                @if ($outCards->count())
                    <li><strong>Trafik Gate Keluar (Out):</strong> jalur keluar terpadat adalah <strong>{{ $outCards[0]['name'] }}</strong>
                        ({{ number_format($outCards[0]['total']) }} transaksi, puncak pada {{ $fmtJam($outCards[0]['peak']['h']) }} sebanyak {{ number_format($outCards[0]['peak']['c']) }} tap)
                        @if ($outCards->count() > 1), diikuti <strong>{{ $outCards[1]['name'] }}</strong> ({{ number_format($outCards[1]['total']) }} transaksi, puncak pada {{ $fmtJam($outCards[1]['peak']['h']) }} sebanyak {{ number_format($outCards[1]['peak']['c']) }} tap)@endif.
                    </li>
                @endif
            </ul>
        </div>
    </div>
</section>

{{-- ============ 2. Metode verifikasi & rasio redundansi sensor ============ --}}
<section class="grid">
    <div class="panel c6">
        <h3>Metode Verifikasi &amp; Rasio Akses</h3>
        <p class="desc">Proporsi Face Recognition vs Kartu Akses.</p>
        <div class="donut-wrap">
            <div class="donut" role="img" aria-label="Face {{ $vFace }}%, Only Card {{ $vCard }}%, Other {{ $vOther }}%"
                 style="background: conic-gradient(var(--in) 0 {{ $vFace }}%, var(--signal) {{ $vFace }}% {{ $vFace + $vCard }}%, #6366f1 {{ $vFace + $vCard }}% 100%);"></div>
            <ul class="donut-leg num">
                <li><i style="background:var(--in)"></i>Face: <strong>{{ number_format($verifikasi['Face'] ?? 0) }}</strong> ({{ $vFace }}%)</li>
                <li><i style="background:var(--signal)"></i>Only Card: <strong>{{ number_format($verifikasi['Only Card'] ?? 0) }}</strong> ({{ $vCard }}%)</li>
                <li><i style="background:#6366f1"></i>Other: <strong>{{ number_format($verifikasi['Other'] ?? 0) }}</strong> ({{ $vOther }}%)</li>
            </ul>
        </div>
    </div>
    <div class="panel c6">
        <h3>Rasio Redundansi Sensor per Gate</h3>
        <p class="desc">Rata-rata tap mentah per transaksi bersih. Makin tinggi, makin banyak tap ganda yang digabung.</p>
        <ul class="ratio">
            @foreach ($redundansi as $name => $r)
                <li><span>{{ $name }}</span><span>{{ number_format($r, 2) }}x tap/tx</span></li>
            @endforeach
        </ul>
    </div>
</section>

{{-- ============ 3. Daftar pegawai tap gate out terakhir per rentang jam ============ --}}
<section class="grid">
    <div class="panel c12" id="last-out">
        <div class="panel-head">
            <div>
                <h3>Daftar Pegawai Tap Gate Out Terakhir (Pukul {{ sprintf('%02d:00 - %02d:59', $jam, $jam) }})
                    <span class="badge-n num">{{ number_format($lastOut->total()) }} Catatan Pegawai</span></h3>
                <p class="desc">Tap keluar (Gate Out) paling akhir dalam sehari per pegawai yang terjadi pada rentang jam ini, sesuai filter aktif.</p>
            </div>
            <form method="GET" action="{{ route('gate.dashboard') }}" class="toolbar">
                @foreach ($keep as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                <select name="jam" onchange="this.form.submit()" aria-label="Rentang jam">
                    @for ($h = 0; $h < 24; $h++)
                        <option value="{{ $h }}" @selected($jam === $h)>Rentang Jam {{ sprintf('%02d:00 - %02d:59', $h, $h) }}</option>
                    @endfor
                </select>
                <a class="btnx xl" href="{{ route('gate.export', ['format' => 'xlsx', 'jenis' => 'last-out', 'jam' => $jam] + $keep) }}">Export Excel</a>
            </form>
        </div>
        <div class="scroll">
            <table>
                <thead><tr><th>No</th><th>Tanggal</th><th>Waktu Tap Out Terakhir</th><th>Perangkat Gate Out</th><th>Personnel ID</th><th>Nama Pegawai</th><th>Unit Organisasi</th><th>Mode Verifikasi</th></tr></thead>
                <tbody>
                @forelse ($lastOut as $i => $r)
                    <tr>
                        <td class="num">{{ $lastOut->firstItem() + $i }}</td>
                        <td class="mono">{{ $r->tap_time->format('Y-m-d') }}</td>
                        <td class="mono">{{ $r->tap_time->format('H:i:s') }}</td>
                        <td>{{ $r->device_name }}</td>
                        <td class="mono">{{ $r->personnel_id }}</td>
                        <td>{{ $r->full_name }}</td>
                        <td>{{ $r->department_name }}</td>
                        <td>{{ $r->verify_mode }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">Tidak ada tap keluar pada rentang jam ini.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="pager num">
            <span>Menampilkan {{ $lastOut->firstItem() ?? 0 }} - {{ $lastOut->lastItem() ?? 0 }} dari {{ number_format($lastOut->total()) }} catatan pegawai</span>
            <span>
                @if ($lastOut->onFirstPage())<span class="dis">Prev</span>@else<a href="{{ $lastOut->previousPageUrl() }}#last-out">Prev</a>@endif
                Hal {{ $lastOut->currentPage() }} / {{ $lastOut->lastPage() }}
                @if ($lastOut->hasMorePages())<a href="{{ $lastOut->nextPageUrl() }}#last-out">Next</a>@else<span class="dis">Next</span>@endif
            </span>
        </div>
    </div>
</section>

{{-- ============ 4. Log eksepsi & status perangkat (gantikan section "Eksepsi terbaru" lama) ============ --}}
<section class="grid">
    <div class="panel c12">
        <div class="panel-head">
            <div>
                <h3>Log Eksepsi &amp; Status Perangkat <span class="badge-n bad num">{{ number_format($exCount) }} Kejadian</span></h3>
                <p class="desc">Sampel kejadian non-standar, akses kartu khusus, dan anomali redundansi tap (≥10 tap dalam &lt;5 detik).</p>
            </div>
            <form method="GET" action="{{ route('gate.dashboard') }}" class="toolbar">
                @foreach ($keep as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                <select name="eks" onchange="this.form.submit()" aria-label="Kategori eksepsi">
                    <option value="">Semua Kejadian Non-Standar &amp; Gangguan</option>
                    <option value="kartu" @selected($eks === 'kartu')>Kartu Khusus</option>
                    <option value="redundansi" @selected($eks === 'redundansi')>Anomali Redundansi</option>
                    <option value="standar" @selected($eks === 'standar')>Event Non-Standar</option>
                </select>
            </form>
        </div>
        <div class="scroll" style="max-height: 420px; overflow-y: auto;">
            <table>
                <thead><tr><th>Waktu Kejadian</th><th>Perangkat Gate</th><th>Kategori / Status</th><th>Deskripsi Event</th><th>Personnel ID</th><th>Nama / Keterangan</th><th>Unit Organisasi</th><th class="r">Tap Digabung</th></tr></thead>
                <tbody>
                @forelse ($exLog as $e)
                    <tr>
                        <td class="mono" style="white-space:nowrap">{{ $e->tap_time->format('Y-m-d H:i:s') }}</td>
                        <td>{{ $e->device_name }}</td>
                        <td><span class="cat {{ $e->kategori === 'Anomali Redundansi' ? 'red' : ($e->kategori === 'Event Non-Standar' ? 'std' : '') }}">{{ $e->kategori }}</span></td>
                        <td>{{ $e->event_description }}</td>
                        <td class="mono">{{ $e->personnel_id }}</td>
                        <td>{{ $e->full_name }}</td>
                        <td>{{ $e->department_name }}</td>
                        <td class="r"><strong>{{ (int) $e->merged_count }}x</strong></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">Tidak ada kejadian pada kategori ini.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>

{{-- ============ 5. Riwayat log transaksi detail ============ --}}
<section class="grid">
    <div class="panel c12" id="riwayat">
        <div class="panel-head">
            <div>
                <h3>Riwayat Log Transaksi Detail</h3>
                <p class="desc">Riwayat lintas gate sesuai filter aktif. Gunakan pencarian ID / nama untuk melacak riwayat individu.</p>
            </div>
            <form method="GET" action="{{ route('gate.dashboard') }}" class="toolbar" id="formRiwayat">
                @foreach ($keep as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                <input type="search" name="q" id="qRiwayat" value="{{ $q }}" placeholder="Filter perorangan (ID / Nama)" aria-label="Cari ID atau nama" autocomplete="off">
                <button class="btnx" type="submit">Cari</button>
                @php $ex = ['jenis' => 'riwayat', 'q' => $q] + $keep; @endphp
                <a class="btnx exp-link" href="{{ route('gate.export', ['format' => 'csv'] + $ex) }}">Export CSV</a>
                <a class="btnx xl exp-link" href="{{ route('gate.export', ['format' => 'xlsx'] + $ex) }}">Export Excel</a>
                <a class="btnx pdf exp-link" href="{{ route('gate.export', ['format' => 'pdf'] + $ex) }}">Export PDF</a>
            </form>
        </div>
        <div id="riwayat-hasil">
        <div class="scroll">
            <table>
                <thead><tr><th>Waktu Tap Pertama</th><th>Perangkat Gate</th><th>Unique Key (Personnel ID)</th><th>Nama Lengkap</th><th>Unit Organisasi</th><th>Mode Verifikasi</th><th>Status</th><th class="r">Jml Tap</th></tr></thead>
                <tbody>
                @forelse ($history as $h)
                    <tr>
                        <td class="mono" style="white-space:nowrap">{{ $h->tap_time->format('Y-m-d H:i:s') }}</td>
                        <td>{{ $h->device_name }}</td>
                        <td class="mono"><a class="pid" data-q="{{ $h->personnel_id }}" href="{{ route('gate.dashboard', ['q' => $h->personnel_id] + $keep) }}#riwayat">{{ $h->personnel_id }}</a></td>
                        <td>{{ $h->full_name }}</td>
                        <td>{{ $h->department_name }}</td>
                        <td>{{ $h->verify_mode }}</td>
                        <td>@if ($h->event_description === \App\Support\GateLogProcessor::NORMAL_EVENT)<span class="in-t">{{ $h->event_description }}</span>@else<span class="pill">{{ $h->event_description }}</span>@endif</td>
                        <td class="r">{{ (int) $h->merged_count }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">Tidak ada transaksi yang cocok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="pager num">
            <span>Menampilkan {{ $history->firstItem() ?? 0 }} - {{ $history->lastItem() ?? 0 }} dari {{ number_format($history->total()) }} transaksi</span>
            <span>
                @if ($history->onFirstPage())<span class="dis">Prev</span>@else<a href="{{ $history->previousPageUrl() }}#riwayat">Prev</a>@endif
                Hal {{ $history->currentPage() }} / {{ number_format($history->lastPage()) }}
                @if ($history->hasMorePages())<a href="{{ $history->nextPageUrl() }}#riwayat">Next</a>@else<span class="dis">Next</span>@endif
            </span>
        </div>
        </div>{{-- /#riwayat-hasil --}}
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

    // Filter kartu gate (arah + unit) di sisi browser
    (function () {
        var a = document.getElementById('fArah'), g = document.getElementById('fGate');
        if (!a || !g) return;
        function apply() {
            document.querySelectorAll('.gcard').forEach(function (c) {
                var ok = (!a.value || c.dataset.arah === a.value) && (!g.value || c.dataset.gate === g.value);
                c.classList.toggle('hide', !ok);
            });
        }
        a.addEventListener('change', apply); g.addEventListener('change', apply);
    })();

    // Pencarian riwayat langsung (tanpa reload halaman, posisi scroll tetap)
    (function () {
        var form = document.getElementById('formRiwayat');
        var input = document.getElementById('qRiwayat');
        var box = document.getElementById('riwayat-hasil');
        if (!form || !input || !box) return;

        var timer = null, ctrl = null;

        function setExportQ() {
            document.querySelectorAll('.exp-link').forEach(function (a) {
                var u = new URL(a.href, location.href);
                u.searchParams.set('q', input.value.trim());
                a.href = u.toString();
            });
        }

        function load(url) {
            if (ctrl) ctrl.abort();
            ctrl = new AbortController();
            box.style.opacity = '.55';
            fetch(url, { signal: ctrl.signal, headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                .then(function (r) { return r.text(); })
                .then(function (html) {
                    var doc = new DOMParser().parseFromString(html, 'text/html');
                    var fresh = doc.getElementById('riwayat-hasil');
                    if (fresh) box.innerHTML = fresh.innerHTML;
                    box.style.opacity = '';
                    history.replaceState(null, '', url.split('#')[0]);
                    setExportQ();
                })
                .catch(function (e) { if (e.name !== 'AbortError') box.style.opacity = ''; });
        }

        function search() {
            var params = new URLSearchParams(new FormData(form));
            params.delete('log_page');
            load(form.action + '?' + params.toString());
        }

        input.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(search, 300);
        });
        form.addEventListener('submit', function (e) { e.preventDefault(); clearTimeout(timer); search(); });

        // Prev/Next dan klik ID personel juga tanpa reload
        box.addEventListener('click', function (e) {
            var a = e.target.closest('a');
            if (!a) return;
            e.preventDefault();
            if (a.classList.contains('pid')) {
                input.value = a.dataset.q;
                search();
            } else {
                load(a.href);
            }
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