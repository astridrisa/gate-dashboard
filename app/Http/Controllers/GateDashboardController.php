<?php

namespace App\Http\Controllers;

use App\Models\GateLog;
use App\Services\GateLogDeduplicatorService;
use App\Support\GateLogProcessor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema; 
use RuntimeException;
use Throwable;

class GateDashboardController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'date'       => ['nullable', 'date_format:Y-m-d'],
            'device'     => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
        ]);

        $dateExpr = $this->dateExpr();
        $hourExpr = $this->hourExpr();
        $normal = GateLogProcessor::NORMAL_EVENT;

        $base = GateLog::query();

        if ($request->filled('date')) {
            $day = Carbon::createFromFormat('Y-m-d', $request->input('date'));
            $base->whereBetween('tap_time', [$day->copy()->startOfDay(), $day->copy()->endOfDay()]);
        }
        if ($request->filled('device')) {
            $base->where('device_name', $request->input('device'));
        }
        if ($request->filled('department')) {
            $base->where('department_name', $request->input('department'));
        }

        // 1. KPI (satu query)
        $kpi = (clone $base)->selectRaw(
            "COUNT(*) AS total,
             COALESCE(SUM(CASE WHEN direction = 'Masuk' THEN 1 ELSE 0 END), 0) AS masuk,
             COALESCE(SUM(CASE WHEN direction = 'Keluar' THEN 1 ELSE 0 END), 0) AS keluar,
             COUNT(DISTINCT personnel_id) AS unique_personnel,
             COALESCE(SUM(CASE WHEN event_description <> ? THEN 1 ELSE 0 END), 0) AS exceptions",
            [$normal]
        )->first();

        $lainnya = (int) $kpi->total - (int) $kpi->masuk - (int) $kpi->keluar;

        // 2. Tren harian
        $dailyTrend = (clone $base)
            ->selectRaw("$dateExpr AS tap_date,
                SUM(CASE WHEN direction = 'Masuk' THEN 1 ELSE 0 END) AS masuk,
                SUM(CASE WHEN direction = 'Keluar' THEN 1 ELSE 0 END) AS keluar")
            ->groupByRaw($dateExpr)
            ->orderByRaw($dateExpr)
            ->get();

        // 3. Distribusi per jam (selalu 24 titik)
        $hourRows = (clone $base)
            ->selectRaw("$hourExpr AS hr,
                SUM(CASE WHEN direction = 'Masuk' THEN 1 ELSE 0 END) AS masuk,
                SUM(CASE WHEN direction = 'Keluar' THEN 1 ELSE 0 END) AS keluar")
            ->groupByRaw($hourExpr)
            ->get()
            ->keyBy(fn ($r) => (int) $r->hr);

        $hourly = [];
        for ($h = 0; $h < 24; $h++) {
            $hourly[] = [
                'hour'   => sprintf('%02d', $h),
                'masuk'  => (int) ($hourRows[$h]->masuk ?? 0),
                'keluar' => (int) ($hourRows[$h]->keluar ?? 0),
            ];
        }

        // 4. Beban per gate
        $gateBreakdown = (clone $base)
            ->select('device_name')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('device_name')
            ->orderBy('device_name')
            ->get();

        // 5. Top 10 departemen
        $topDepartments = (clone $base)
            ->select('department_name')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('department_name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // 6. Top 15 personel
        $topPersonnel = (clone $base)
            ->whereNotNull('personnel_id')
            ->select('personnel_id', 'full_name', 'department_name')
            ->selectRaw("COUNT(*) AS total,
                SUM(CASE WHEN direction = 'Masuk' THEN 1 ELSE 0 END) AS masuk,
                SUM(CASE WHEN direction = 'Keluar' THEN 1 ELSE 0 END) AS keluar,
                COUNT(DISTINCT $dateExpr) AS active_days")
            ->groupBy('personnel_id', 'full_name', 'department_name')
            ->orderByDesc('total')
            ->limit(15)
            ->get();

        // 7. Eksepsi / anomali
        $exceptions = (clone $base)
            ->where('event_description', '<>', $normal)
            ->orderByDesc('tap_time')
            ->limit(50)
            ->get();

        // Opsi dropdown filter
        $filterDates = GateLog::query()->selectRaw("$dateExpr AS tap_date")->distinct()->orderBy('tap_date')->pluck('tap_date');
        $filterDevices = GateLog::query()->distinct()->orderBy('device_name')->pluck('device_name');
        $filterDepartments = GateLog::query()->distinct()->orderBy('department_name')->pluck('department_name');

        // ====================================================================
        // [TAMBAHAN 2] Data untuk bagian baru di view (tidak mengubah kode di atas)
        // ====================================================================
        $table = (new GateLog)->getTable();

        // Kolom opsional: dicari otomatis. Ubah daftarnya kalau nama kolommu beda.
        $vCol = $this->firstColumn(['verify_mode', 'verification_mode', 'verify_type', 'verification']);
        $mCol = $this->firstColumn(['merged_count', 'tap_count', 'jumlah_tap']);
        $mergedExpr = $mCol ?: '1';
        $gandaRow  = (clone $base)->selectRaw("COALESCE(SUM($mergedExpr), 0) AS mentah, COUNT(*) AS bersih")->first();
        $tapMentah = $mCol ? (int) $gandaRow->mentah : null;
        $tapGanda  = $mCol ? max((int) $gandaRow->mentah - (int) $gandaRow->bersih, 0) : null;

        // A. Kartu analisa tiap gate
        $grandTotal = max((int) $kpi->total, 1);
        $perGate = (clone $base)
            ->selectRaw("device_name, $hourExpr AS h, COUNT(*) AS c")
            ->groupBy('device_name')->groupByRaw($hourExpr)
            ->get()
            ->groupBy('device_name');

        $gateCards = $perGate->map(function ($rows, $name) use ($grandTotal) {
            $total  = (int) $rows->sum('c');
            $sorted = $rows->sortByDesc('c')->values();
            $mk = fn ($r) => $r ? [
                'h'   => (int) $r->h,
                'c'   => (int) $r->c,
                'pct' => round($r->c / max($total, 1) * 100, 1),
            ] : null;

            return [
                'name'  => (string) $name,
                'arah'  => preg_match('/(out|keluar)/i', (string) $name) ? 'out' : 'in',
                'total' => $total,
                'share' => round($total / $grandTotal * 100, 1),
                'peak'  => $mk($sorted[0]),
                'peak2' => $mk($sorted[1] ?? null),
            ];
        })->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();

        // B. Metode verifikasi
        if ($vCol) {
            $verifikasi = (clone $base)
                ->selectRaw("CASE WHEN LOWER($vCol) LIKE '%face%' THEN 'Face'
                                  WHEN LOWER($vCol) LIKE '%card%' THEN 'Only Card'
                                  ELSE 'Other' END AS m, COUNT(*) AS c")
                ->groupBy('m')->pluck('c', 'm');
        } else {
            $verifikasi = collect();
        }

        // C. Rasio redundansi sensor per gate
        $redundansi = (clone $base)
            ->selectRaw("device_name, SUM($mergedExpr) * 1.0 / COUNT(*) AS r")
            ->groupBy('device_name')->orderBy('device_name')
            ->pluck('r', 'device_name')
            ->map(fn ($v) => (float) $v);

        // D. Tap keluar terakhir per pegawai per hari pada rentang jam
        $jam = max(0, min(23, (int) $request->input('jam', 16)));
        $lastSub = (clone $base)
            ->where('direction', 'Keluar')
            ->whereNotNull('personnel_id')
            ->whereRaw("$hourExpr = ?", [$jam])
            ->selectRaw('personnel_id, MAX(tap_time) AS last_time')
            ->groupBy('personnel_id')->groupByRaw($dateExpr);

        $lastOut = GateLog::query()
            ->joinSub($lastSub, 'l', function ($j) use ($table) {
                $j->on("$table.personnel_id", '=', 'l.personnel_id')
                  ->on("$table.tap_time", '=', 'l.last_time');
            })
            ->where("$table.direction", 'Keluar')
            ->select("$table.*")
            ->orderByDesc("$table.tap_time")
            ->paginate(15, ['*'], 'out_page')->withQueryString();

        // E. Log eksepsi: non-standar, kartu khusus, redundansi >= 10 tap
        $eks = in_array($request->input('eks'), ['kartu', 'redundansi', 'standar'], true) ? $request->input('eks') : null;
        $cardOnly = $vCol ? "(LOWER($vCol) LIKE '%card%' AND LOWER($vCol) NOT LIKE '%face%')" : null;

        $exQuery = (clone $base)->where(function ($q) use ($normal, $cardOnly, $mCol) {
            $q->where('event_description', '<>', $normal);
            if ($cardOnly) { $q->orWhereRaw($cardOnly); }
            if ($mCol)     { $q->orWhere($mCol, '>=', 10); }
        });
        if ($eks === 'kartu' && $cardOnly)  { $exQuery->whereRaw($cardOnly); }
        if ($eks === 'redundansi' && $mCol) { $exQuery->where($mCol, '>=', 10); }
        if ($eks === 'standar')             { $exQuery->where('event_description', '<>', $normal); }

        $exCount = (clone $exQuery)->count();
        $exLog = $exQuery->orderByDesc('tap_time')->limit(100)->get()->each(function ($e) use ($vCol, $mCol) {
            $mode = $vCol ? strtolower((string) $e->{$vCol}) : '';
            $e->verify_mode  = $vCol ? $e->{$vCol} : '-';
            $e->merged_count = $mCol ? (int) $e->{$mCol} : 1;
            $e->kategori = $e->merged_count >= 10 ? 'Anomali Redundansi'
                : (str_contains($mode, 'card') && ! str_contains($mode, 'face') ? 'Kartu Khusus' : 'Event Non-Standar');
        });

        // F. Riwayat transaksi detail (+ pencarian ID / nama)
        $q = mb_substr(trim((string) $request->input('q')), 0, 100);
        $history = (clone $base)
            ->when($q !== '', fn ($x) => $x->where(fn ($w) => $w
                ->where('personnel_id', 'like', "%$q%")
                ->orWhere('full_name', 'like', "%$q%")))
            ->orderByDesc('tap_time')
            ->paginate(20, ['*'], 'log_page')->withQueryString();

        // Properti virtual supaya view seragam walau nama kolom berbeda
        foreach ([$lastOut, $history] as $paginator) {
            $paginator->getCollection()->each(function ($r) use ($vCol, $mCol) {
                $r->verify_mode  = $vCol ? $r->{$vCol} : '-';
                $r->merged_count = $mCol ? (int) $r->{$mCol} : 1;
            });
        }
        // ==================== akhir [TAMBAHAN 2] ====================

        return view('gate-dashboard', compact(
            'kpi',
            'lainnya',
            'dailyTrend',
            'hourly',
            'gateBreakdown',
            'topDepartments',
            'topPersonnel',
            'exceptions',
            'filterDates',
            'filterDevices',
            'filterDepartments',
            'gateCards',
            'verifikasi',
            'redundansi',
            'jam',
            'lastOut',
            'eks',
            'exCount',
            'exLog',
            'q',
            'history',
            'tapGanda',
            'tapMentah'
        ));
    }

    public function import(Request $request, GateLogDeduplicatorService $service)
    {
        $request->validate([
            'log_file'        => ['required', 'file', 'extensions:xls,xlsx,csv', 'max:51200'],
            'import_password' => ['required', 'string'],
        ]);

        $file = $request->file('log_file');

        $expected = (string) config('gate.import_password');
        if ($expected === '') {
            return back()->withErrors([
                'import_password' => 'GATE_IMPORT_PASSWORD belum diatur di file .env.'
            ]);
        }

        if (! hash_equals($expected, (string) $request->input('import_password'))) {
            return back()->withErrors([
                'import_password' => 'Kata sandi import salah.'
            ]);
        }

        $expected = (string) config('gate.import_password');
        if ($expected === '') {
            return back()->withErrors(['import_password' => 'GATE_IMPORT_PASSWORD belum diatur di file .env.']);
        }
        if (! hash_equals($expected, (string) $request->input('import_password'))) {
            return back()->withErrors(['import_password' => 'Kata sandi import salah.']);
        }

        // Import file besar butuh waktu & memori lebih
        set_time_limit(0);
        @ini_set('memory_limit', '1024M');

        try {
            $stats = $service->processAndImport(
                $request->file('log_file'),
                $request->boolean('replace')
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['log_file' => $e->getMessage()]);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors([
                'log_file' => 'Import gagal. Detail ada di storage/logs/laravel.log.',
            ]);
        }

        $message = "Berhasil mengimpor {$stats['merged']} data bersih "
            . "(menggabungkan {$stats['removed']} tap ganda < " . GateLogProcessor::DEDUP_WINDOW_SECONDS
            . " detik dari {$stats['raw']} baris awal).";
        if ($stats['invalid'] > 0) {
            $message .= " {$stats['invalid']} baris dilewati karena format waktu tidak valid.";
        }

        return redirect()->route('gate.dashboard')->with('success', $message);
    }

    public function clear(Request $request)
    {
        $request->validate([
            'clear_password' => ['required', 'string'],
        ]);

        $expected = (string) config('gate.clear_password');
        if ($expected === '') {
            return back()->withErrors(['clear_password' => 'GATE_CLEAR_PASSWORD belum diatur di file .env.']);
        }
        if (! hash_equals($expected, (string) $request->input('clear_password'))) {
            return back()->withErrors(['clear_password' => 'Kata sandi clear salah.']);
        }

        GateLog::query()->delete();

        return redirect()->route('gate.dashboard')->with('success', 'Semua data log gate berhasil dihapus.');
    }

    /** Export riwayat detail / tap-out terakhir: csv, xlsx (openspout), pdf (dompdf). */
    public function export(Request $request, string $format)
    {
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 404);

        $vCol = $this->firstColumn(['verify_mode', 'verification_mode', 'verify_type', 'verification']);
        $mCol = $this->firstColumn(['merged_count', 'tap_count', 'jumlah_tap']);

        $query = GateLog::query();
        if ($request->filled('date') && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->input('date'))) {
            $day = Carbon::createFromFormat('Y-m-d', $request->input('date'));
            $query->whereBetween('tap_time', [$day->copy()->startOfDay(), $day->copy()->endOfDay()]);
        }
        if ($request->filled('device')) {
            $query->where('device_name', $request->input('device'));
        }
        if ($request->filled('department')) {
            $query->where('department_name', $request->input('department'));
        }

        $q = mb_substr(trim((string) $request->input('q')), 0, 100);
        if ($q !== '') {
            $query->where(fn ($w) => $w->where('personnel_id', 'like', "%$q%")->orWhere('full_name', 'like', "%$q%"));
        }
        if ($request->input('jenis') === 'last-out') {
            $query->where('direction', 'Keluar')
                  ->whereRaw($this->hourExpr() . ' = ?', [max(0, min(23, (int) $request->input('jam', 16)))]);
        }
        $query->orderBy('tap_time');

        $header = ['Waktu', 'Gate', 'Personnel ID', 'Nama', 'Unit', 'Mode', 'Status', 'Jml Tap'];
        $toRow = fn ($r) => [
            (string) $r->tap_time, (string) $r->device_name, (string) $r->personnel_id, (string) $r->full_name,
            (string) $r->department_name, $vCol ? (string) $r->{$vCol} : '-', (string) $r->event_description,
            $mCol ? (int) $r->{$mCol} : 1,
        ];
        $fileBase = 'log-gate-' . now()->format('Ymd-His');

        // ---------- XLSX  ----------
        if ($format === 'xlsx') {
            if (! class_exists(\OpenSpout\Writer\XLSX\Writer::class)) {
                return back()->withErrors(['log_file' => 'Export Excel butuh paket. Jalankan: composer require openspout/openspout']);
            }
            set_time_limit(0);

            return response()->streamDownload(function () use ($query, $header, $toRow) {
                $writer = new \OpenSpout\Writer\XLSX\Writer();
                $writer->openToFile('php://output');
                $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues($header));
                foreach ($query->lazy(2000) as $r) {
                    $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues($toRow($r)));
                }
                $writer->close();
            }, $fileBase . '.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        }

        // ---------- PDF ----------
        if ($format === 'pdf') {
            if (! class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
                return back()->withErrors(['log_file' => 'Export PDF butuh paket. Jalankan: composer require barryvdh/laravel-dompdf']);
            }
            $limit = 500;
            set_time_limit(0);
            @ini_set('memory_limit', '1024M');
            $total = (clone $query)->count();
            $rows = $query->limit($limit)->get()->map($toRow);

            return \Barryvdh\DomPDF\Facade\Pdf::loadView('gate-export', [
                'header' => $header,
                'rows'   => $rows,
                'total'  => $total,
                'limit'  => $limit,
            ])->setPaper('a4', 'landscape')->download($fileBase . '.pdf');
        }

        // ---------- CSV ----------
        return response()->streamDownload(function () use ($query, $header, $toRow) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
            fputcsv($out, $header);
            foreach ($query->lazy(2000) as $r) {
                fputcsv($out, $toRow($r));
            }
            fclose($out);
        }, $fileBase . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Cari nama kolom pertama yang benar-benar ada di tabel (null kalau tidak ada). */
    private function firstColumn(array $candidates): ?string
    {
        $table = (new GateLog)->getTable();
        foreach ($candidates as $c) {
            if (Schema::hasColumn($table, $c)) {
                return $c;
            }
        }
        return null;
    }

    /** Ekspresi SQL "tanggal dari tap_time" sesuai driver database. */
    private function dateExpr(): string
    {
        return match (DB::getDriverName()) {
            'sqlite' => 'date(tap_time)',
            'pgsql'  => 'CAST(tap_time AS DATE)',
            default  => 'DATE(tap_time)',      // mysql / mariadb
        };
    }

    /** Ekspresi SQL "jam (0-23) dari tap_time" sesuai driver database. */
    private function hourExpr(): string
    {
        return match (DB::getDriverName()) {
            'sqlite' => "CAST(strftime('%H', tap_time) AS INTEGER)",
            'pgsql'  => 'CAST(EXTRACT(HOUR FROM tap_time) AS INTEGER)',
            default  => 'HOUR(tap_time)',
        };
    }
}