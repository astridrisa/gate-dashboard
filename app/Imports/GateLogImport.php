<?php

namespace App\Imports;

use App\Support\GateLogProcessor;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;

/**
 * Membaca file per potongan (chunk) supaya memori PhpSpreadsheet tidak membengkak,
 * dan hanya menyimpan record ringan yang dibutuhkan untuk deduplikasi.
 */
class GateLogImport implements ToCollection, WithChunkReading
{
    /** @var array<int, array> */
    public array $records = [];

    public int $rawRows = 0;
    public int $invalidRows = 0;

    private bool $headerFound = false;
    private int $index = 0;

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $row = $row instanceof Collection ? $row->all() : (array) $row;

            // Lewati judul/baris sebelum header "Time" (dan header yang berulang)
            if (GateLogProcessor::isHeaderRow($row)) {
                $this->headerFound = true;
                continue;
            }
            if (! $this->headerFound) {
                continue;
            }

            $this->index++;

            if (GateLogProcessor::isBlankRow($row)) {
                continue;
            }

            $this->rawRows++;

            try {
                $this->records[] = GateLogProcessor::parseRow($row, $this->index);
            } catch (InvalidArgumentException) {
                $this->invalidRows++;
            }
        }
    }

    public function chunkSize(): int
    {
        return 2000;
    }

    public function headerFound(): bool
    {
        return $this->headerFound;
    }
}
