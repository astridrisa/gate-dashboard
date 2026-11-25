<?php

namespace App\Http\Controllers;

use App\Models\GateLog;
use App\Services\GateLogDeduplicatorService;
use App\Support\GateLogProcessor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'filterDepartments'
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