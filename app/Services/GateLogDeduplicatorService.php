<?php

namespace App\Services;

use App\Imports\GateLogImport;
use App\Models\GateLog;
use App\Support\GateLogProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelFormat;
use PhpOffice\PhpSpreadsheet\IOFactory as PhpSpreadsheetIOFactory;
use RuntimeException;
use ZipArchive;

class GateLogDeduplicatorService
{
    /**
     * Baca file XLS/XLSX/CSV, gabungkan tap ganda, lalu simpan ke database
     * dalam satu transaksi (jika gagal, data lama tetap utuh).
     *
     * @return array{raw:int, merged:int, removed:int, invalid:int}
     */
    public function processAndImport(UploadedFile $file, bool $replaceExisting = true): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        
        if ($extension === 'csv') {
            $readerType = ExcelFormat::CSV;

        } else {
            $path = $file->getRealPath();

            $signature = file_get_contents($path, false, null, 0, 8);

            // XLSX: file ZIP dengan struktur workbook Excel
            if (str_starts_with($signature, "PK\x03\x04")) {
                $zip = new ZipArchive();

                if ($zip->open($path) !== true) {
                    throw new RuntimeException(
                        'File Excel tidak dapat dibuka.'
                    );
                }

                $isXlsx = (
                    $zip->locateName('[Content_Types].xml') !== false &&
                    $zip->locateName('xl/workbook.xml') !== false
                );

                $zip->close();

                if (! $isXlsx) {
                    throw new RuntimeException(
                        'Struktur file ZIP bukan workbook XLSX.'
                    );
                }

                $readerType = ExcelFormat::XLSX;

            // XLS lama: format OLE Compound Document
            } elseif (str_starts_with(
                $signature,
                "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1"
            )) {
                $readerType = ExcelFormat::XLS;

            } else {
                throw new RuntimeException(
                    'Format file tidak dikenali.'
                );
            }
        }

        $import = new GateLogImport();

        Excel::import($import, $file, null, $readerType);

        if (! $import->headerFound()) {
            throw new RuntimeException(
                'Baris header tidak ditemukan. Kolom pertama pada baris judul harus bernama "Time".'
            );
        }

        $clean = GateLogProcessor::deduplicate($import->records);
        $import->records = []; // lepaskan memori

        $now = now()->toDateTimeString();
        $rows = array_map(
            static fn (array $r) => GateLogProcessor::toDbRow($r, $now),
            $clean
        );
        unset($clean);

        DB::transaction(function () use ($rows, $replaceExisting) {
            if ($replaceExisting) {
                GateLog::query()->delete();
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('gate_logs')->insert($chunk);
            }
        });

        $merged = count($rows);

        return [
            'raw'     => $import->rawRows,
            'merged'  => $merged,
            'removed' => $import->rawRows - $import->invalidRows - $merged,
            'invalid' => $import->invalidRows,
        ];
    }
}
