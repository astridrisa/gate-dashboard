<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use InvalidArgumentException;

/**
 * Logika murni pengolah log gate (tanpa ketergantungan Laravel),
 * sehingga mudah diuji dan dipakai ulang.
 *
 * Urutan kolom file sumber (indeks 0..12):
 * Time, Area Name, Device Name, Event Point, Event Description, Event Level,
 * Personnel ID, First Name, Last Name, Card Number, Department Name,
 * Reader Name, Verification Mode
 */
final class GateLogProcessor
{
    /** Tap yang sama (orang + device + event) dalam rentang ini dianggap duplikat. */
    public const DEDUP_WINDOW_SECONDS = 5;

    public const NORMAL_EVENT = 'Normal Verify Open';
    public const DEFAULT_DEPARTMENT = 'Tanpa Departemen';
    public const UNKNOWN_NAME = 'Tidak Terdaftar / Sistem';

    public static function isHeaderRow(array $row): bool
    {
        return isset($row[0]) && strcasecmp(trim((string) $row[0]), 'Time') === 0;
    }

    public static function isBlankRow(array $row): bool
    {
        return trim((string) ($row[0] ?? '')) === '';
    }

    /**
     * Ubah satu baris mentah menjadi record bersih.
     *
     * @throws InvalidArgumentException jika kolom waktu tidak bisa dibaca
     */
    public static function parseRow(array $row, int $index): array
    {
        $time = self::parseDateTime($row[0] ?? null);

        $deviceName = self::str($row[2] ?? null);
        $readerName = self::str($row[11] ?? null);
        $personnelId = self::cleanId($row[6] ?? null);
        $cardNumber = self::cleanId($row[9] ?? null);
        $deptName = self::str($row[10] ?? null);

        $fullName = trim(self::str($row[7] ?? null) . ' ' . self::str($row[8] ?? null));

        return [
            'orig_index'        => $index,
            'timestamp'         => $time->getTimestamp(),
            'tap_time'          => $time->format('Y-m-d H:i:s'),
            'entity_key'        => $personnelId ?? $cardNumber,
            'area_name'         => self::str($row[1] ?? null),
            'device_name'       => $deviceName,
            'direction'         => self::detectDirection($deviceName, $readerName),
            'event_point'       => self::str($row[3] ?? null),
            'event_description' => self::str($row[4] ?? null),
            'event_level'       => self::str($row[5] ?? null),
            'personnel_id'      => $personnelId,
            'full_name'         => $fullName !== '' ? $fullName : self::UNKNOWN_NAME,
            'card_number'       => $cardNumber,
            'department_name'   => $deptName !== '' ? $deptName : self::DEFAULT_DEPARTMENT,
            'reader_name'       => $readerName,
            'verification_mode' => self::str($row[12] ?? null),
        ];
    }

    /**
     * Cocokkan kata "In"/"Out" (atau "Masuk"/"Keluar") sebagai kata utuh,
     * bukan potongan huruf: "Parking Out" tidak boleh terbaca "In".
     * Pemisah yang dikenali: spasi, '-', '_', angka, dll. (selain huruf).
     */
    public static function detectDirection(string $deviceName, string $readerName = ''): string
    {
        foreach ([$deviceName, $readerName] as $text) {
            if (preg_match('/(?<![a-z])(out|keluar)(?![a-z])/i', $text)) {
                return 'Keluar';
            }
            if (preg_match('/(?<![a-z])(in|masuk)(?![a-z])/i', $text)) {
                return 'Masuk';
            }
        }

        return 'Lainnya';
    }

    /**
     * Terima serial Excel (angka), string tanggal ISO, atau d/m/Y (format Indonesia).
     *
     * @throws InvalidArgumentException
     */
    public static function parseDateTime(mixed $value): DateTimeImmutable
    {
        $utc = new DateTimeZone('UTC');

        if ($value instanceof \DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        // Serial tanggal Excel (hari sejak 1899-12-30, pecahan = jam)
        if ((is_int($value) || is_float($value) || (is_string($value) && is_numeric($value)))
            && (float) $value >= 1 && (float) $value < 2958466) {
            $seconds = (int) round(((float) $value) * 86400);

            return (new DateTimeImmutable('1899-12-30 00:00:00', $utc))
                ->modify(sprintf('+%d seconds', $seconds));
        }

        $str = trim((string) $value);

        // d/m/Y [H:i[:s]]  -> diperlakukan sebagai hari/bulan/tahun
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})(?:[ T](\d{1,2}):(\d{2})(?::(\d{2}))?)?$#', $str, $m)) {
            if (! checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
                throw new InvalidArgumentException("Tanggal tidak valid: {$str}");
            }

            return (new DateTimeImmutable('now', $utc))
                ->setDate((int) $m[3], (int) $m[2], (int) $m[1])
                ->setTime((int) ($m[4] ?? 0), (int) ($m[5] ?? 0), (int) ($m[6] ?? 0));
        }

        if ($str === '') {
            throw new InvalidArgumentException('Waktu kosong.');
        }

        try {
            return new DateTimeImmutable($str, $utc);
        } catch (Exception $e) {
            throw new InvalidArgumentException("Format waktu tidak dikenali: {$str}", 0, $e);
        }
    }

    /** Bersihkan ID/nomor kartu (hindari "123.0" atau 1.23E+12 dari float Excel). */
    public static function cleanId(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            if (is_finite($value) && floor($value) === $value) {
                return sprintf('%.0f', $value);
            }

            return (string) $value;
        }

        $str = trim((string) $value);
        if (preg_match('/^-?\d+\.0+$/', $str)) {
            $str = (string) preg_replace('/\.0+$/', '', $str);
        }

        return $str === '' ? null : $str;
    }

    /**
     * Buang tap ganda: orang yang sama, di device yang sama, dengan event yang sama,
     * dalam < DEDUP_WINDOW_SECONDS dari tap yang DIPERTAHANKAN.
     * Contoh: tap pada detik 0, 3, 6 -> yang disimpan detik 0 dan 6.
     *
     * Baris tanpa Personnel ID maupun Card Number tidak pernah digabung.
     *
     * @param  array<int, array> $records hasil parseRow()
     * @return array<int, array>
     */
    public static function deduplicate(array $records): array
    {
        // Kronologis naik. Jika detik sama, baris yang lebih bawah di file didahulukan
        // (file ekspor umumnya berurutan terbaru -> terlama).
        usort($records, static function (array $a, array $b): int {
            if ($a['timestamp'] === $b['timestamp']) {
                return $b['orig_index'] <=> $a['orig_index'];
            }

            return $a['timestamp'] <=> $b['timestamp'];
        });

        $lastKept = [];
        $clean = [];

        foreach ($records as $r) {
            if ($r['entity_key'] === null) {
                $clean[] = $r;
                continue;
            }

            $key = $r['entity_key'] . '|' . $r['device_name'] . '|' . $r['event_description'];

            if (isset($lastKept[$key]) && ($r['timestamp'] - $lastKept[$key]) < self::DEDUP_WINDOW_SECONDS) {
                continue;
            }

            $lastKept[$key] = $r['timestamp'];
            $clean[] = $r;
        }

        return $clean;
    }

    /** Ubah record bersih menjadi baris siap-insert ke tabel gate_logs. */
    public static function toDbRow(array $r, string $now): array
    {
        return [
            'tap_time'          => $r['tap_time'],
            'area_name'         => $r['area_name'],
            'device_name'       => $r['device_name'],
            'direction'         => $r['direction'],
            'event_point'       => $r['event_point'],
            'event_description' => $r['event_description'],
            'event_level'       => $r['event_level'],
            'personnel_id'      => $r['personnel_id'],
            'full_name'         => $r['full_name'],
            'card_number'       => $r['card_number'],
            'department_name'   => $r['department_name'],
            'reader_name'       => $r['reader_name'],
            'verification_mode' => $r['verification_mode'],
            'created_at'        => $now,
            'updated_at'        => $now,
        ];
    }

    private static function str(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }
}
