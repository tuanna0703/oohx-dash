<?php

namespace App\Services\ScreenImport;

use Generator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Minimal wrapper quanh PhpSpreadsheet: extract header + sample rows,
 * và cung cấp iterator cho full data rows.
 *
 * Giả định: sheet đầu tiên, row 1 = headers, row 2+ = data.
 * Support: .xlsx, .xls, .csv (CSV được PhpSpreadsheet tự detect).
 */
class SpreadsheetReader
{
    public const SAMPLE_SIZE = 5;
    public const MAX_ROWS    = 5000;

    private Spreadsheet $spreadsheet;

    public function __construct(string $filePath)
    {
        $this->spreadsheet = IOFactory::load($filePath);
    }

    /**
     * Analyze file: headers + sample rows + total row count.
     *
     * @return array{headers:list<string>, sample_rows:list<list<mixed>>, total_rows:int}
     */
    public function analyze(): array
    {
        $sheet = $this->spreadsheet->getSheet(0);
        $rows  = $sheet->toArray(null, true, true, false);

        // Skip leading empty rows (some templates have title banners on top)
        $dataStart = 0;
        foreach ($rows as $i => $r) {
            if (! self::isEmpty($r)) { $dataStart = $i; break; }
        }

        $headerRow = $rows[$dataStart] ?? [];
        $headers   = array_map(
            fn ($v) => trim((string) ($v ?? '')),
            $headerRow
        );

        // Drop trailing empty header columns
        while (count($headers) > 0 && $headers[array_key_last($headers)] === '') {
            array_pop($headers);
        }

        $dataRows = array_slice($rows, $dataStart + 1);
        $dataRows = array_values(array_filter($dataRows, fn ($r) => ! self::isEmpty($r)));

        $sample = array_slice($dataRows, 0, self::SAMPLE_SIZE);
        // Trim sample cells to header count
        $sample = array_map(
            fn ($r) => array_slice(array_pad($r, count($headers), null), 0, count($headers)),
            $sample
        );

        return [
            'headers'     => array_values($headers),
            'sample_rows' => $sample,
            'total_rows'  => count($dataRows),
        ];
    }

    /**
     * Iterator over all data rows (not just sample).
     *
     * Yields [row_number_1_indexed, [cell values aligned to header width]]
     *
     * @return Generator<int, array{int, list<mixed>}>
     */
    public function iterate(int $headerColumnCount): Generator
    {
        $sheet = $this->spreadsheet->getSheet(0);
        $rows  = $sheet->toArray(null, true, true, false);

        $dataStart = 0;
        foreach ($rows as $i => $r) {
            if (! self::isEmpty($r)) { $dataStart = $i; break; }
        }

        $rowNumber = 0;
        foreach (array_slice($rows, $dataStart + 1) as $r) {
            $rowNumber++;
            if (self::isEmpty($r)) continue;
            if ($rowNumber > self::MAX_ROWS) break;

            $aligned = array_slice(array_pad($r, $headerColumnCount, null), 0, $headerColumnCount);
            yield [$rowNumber + $dataStart + 1, $aligned]; // 1-indexed spreadsheet row number
        }
    }

    private static function isEmpty(array $row): bool
    {
        foreach ($row as $v) {
            if ($v !== null && trim((string) $v) !== '') return false;
        }
        return true;
    }
}
