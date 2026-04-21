<?php

namespace App\Services\ScreenImport;

use App\Models\ScreenImport;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Xuất file Excel các row có lỗi (original values + error message).
 *
 * Output: lưu vào storage disk `local`, path lưu ở import.error_report_path
 * để user download qua Filament action.
 *
 * Format:
 *   Sheet "Errors":
 *     Col A: Row #
 *     Col B..N: Original cell values (từ file gốc)
 *     Col X: Errors (concat bằng " · ")
 */
class ErrorReportExporter
{
    public function generate(ScreenImport $import): string
    {
        $errors  = $import->validation_errors ?? [];
        $headers = $import->headers ?? [];

        if (empty($errors)) {
            throw new \RuntimeException('Không có rows lỗi để export.');
        }

        $sourcePath = Storage::disk('local')->path($import->file_path);
        $sourceSpreadsheet = IOFactory::load($sourcePath);
        $sourceRows = $sourceSpreadsheet->getSheet(0)->toArray(null, true, true, false);

        $out = new Spreadsheet();
        $sheet = $out->getActiveSheet();
        $sheet->setTitle('Errors');

        $sheet->setCellValue([1, 1], '#');
        foreach ($headers as $i => $h) {
            $sheet->setCellValue([2 + $i, 1], $h);
        }
        $errorCol = 2 + count($headers);
        $sheet->setCellValue([$errorCol, 1], 'Errors');

        $errorColLetter = Coordinate::stringFromColumnIndex($errorCol);
        $headerRange = "A1:{$errorColLetter}1";
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID);
        $sheet->getStyle($headerRange)->getFill()->getStartColor()->setRGB('FEE2E2');

        $outRow = 2;
        foreach ($errors as $spreadsheetRow => $rowErrors) {
            $srcRowIdx = ((int) $spreadsheetRow) - 1;
            $srcRow = $sourceRows[$srcRowIdx] ?? [];

            $sheet->setCellValue([1, $outRow], $spreadsheetRow);
            foreach ($headers as $i => $_) {
                $v = $srcRow[$i] ?? null;
                if ($v !== null) {
                    $sheet->setCellValue([2 + $i, $outRow], $v);
                }
            }
            $sheet->setCellValue(
                [$errorCol, $outRow],
                is_array($rowErrors) ? implode(' · ', $rowErrors) : (string) $rowErrors
            );
            $outRow++;
        }

        for ($col = 1; $col <= $errorCol; $col++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setAutoSize(true);
        }

        $filename = sprintf(
            'screen-imports/errors/errors-%s-%s.xlsx',
            now()->format('Ymd-His'),
            substr($import->id, 0, 8)
        );
        $fullPath = Storage::disk('local')->path($filename);
        @mkdir(dirname($fullPath), 0755, true);

        $writer = IOFactory::createWriter($out, 'Xlsx');
        $writer->save($fullPath);

        $import->update(['error_report_path' => $filename]);

        return $filename;
    }
}
