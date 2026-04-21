<?php

namespace App\Services\ScreenImport;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Sinh template xlsx với canonical headers từ FieldCatalog.
 *
 * 3 sheets:
 *   1. "Screens" — row 1 là labels, row 2 là field keys (for manual mapping)
 *      Nếu user upload template này, AI mapping sẽ match 100% dễ dàng.
 *   2. "Instructions" — hướng dẫn ngắn
 *   3. "Field Reference" — full catalog: key, label, type, required, enum
 */
class TemplateGenerator
{
    public function generate(): string
    {
        $out = new Spreadsheet();

        // ── Sheet 1: Screens template ────────────────────────────────────
        $sheet = $out->getActiveSheet();
        $sheet->setTitle('Screens');

        $fields = FieldCatalog::all();
        foreach ($fields as $i => $f) {
            $col = $i + 1;
            $sheet->setCellValue([$col, 1], $f['label'] . (empty($f['required']) ? '' : ' *'));
            $sheet->setCellValue([$col, 2], $f['key']);

            $parts = [];
            if (! empty($f['required'])) $parts[] = 'REQUIRED';
            $parts[] = 'Type: ' . $f['type'];
            if (! empty($f['enum'])) $parts[] = 'Values: ' . implode(', ', $f['enum']);
            if (! empty($f['description'])) $parts[] = $f['description'];

            $coordinate = Coordinate::stringFromColumnIndex($col) . '1';
            $sheet->getComment($coordinate)
                ->getText()->createText(implode("\n", $parts));
        }

        $lastColLetter = Coordinate::stringFromColumnIndex(count($fields));
        $sheet->getStyle("A1:{$lastColLetter}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastColLetter}1")->getFill()->setFillType(Fill::FILL_SOLID);
        $sheet->getStyle("A1:{$lastColLetter}1")->getFill()->getStartColor()->setRGB('DBEAFE');

        $sheet->getStyle("A2:{$lastColLetter}2")->getFont()->setItalic(true);
        $sheet->getStyle("A2:{$lastColLetter}2")->getFont()->getColor()->setRGB('6B7280');

        for ($c = 1; $c <= count($fields); $c++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
        }

        // ── Sheet 2: Instructions ────────────────────────────────────────
        $inst = $out->createSheet();
        $inst->setTitle('Instructions');
        $instRows = [
            'Screen Import Template',
            '',
            '1. Row 1 = tên cột (label) — có thể đổi hoặc để nguyên.',
            '2. Row 2 = DB field key — XÓA ROW NÀY trước khi điền data.',
            '3. Row 3+ = data. Cột không dùng → để trống (không xóa cột).',
            '',
            'Số lượng tối đa: 5000 rows mỗi file.',
            'Kích thước max: 10MB.',
            '',
            'Nếu upload file có layout khác template, AI vẫn sẽ map tự động.',
            'Template này chỉ giúp match 100% mà không cần AI inference.',
            '',
            'Xem Sheet "Field Reference" để biết chi tiết từng field.',
        ];
        foreach ($instRows as $i => $line) {
            $inst->setCellValue([1, $i + 1], $line);
        }
        $inst->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $inst->getColumnDimension('A')->setWidth(80);

        // ── Sheet 3: Field Reference ─────────────────────────────────────
        $ref = $out->createSheet();
        $ref->setTitle('Field Reference');
        $ref->setCellValue('A1', 'Key');
        $ref->setCellValue('B1', 'Group');
        $ref->setCellValue('C1', 'Label');
        $ref->setCellValue('D1', 'Type');
        $ref->setCellValue('E1', 'Required');
        $ref->setCellValue('F1', 'Enum values');
        $ref->setCellValue('G1', 'Description');
        $ref->getStyle('A1:G1')->getFont()->setBold(true);

        $r = 2;
        foreach ($fields as $f) {
            $ref->setCellValue([1, $r], $f['key']);
            $ref->setCellValue([2, $r], $f['group']);
            $ref->setCellValue([3, $r], $f['label']);
            $ref->setCellValue([4, $r], $f['type']);
            $ref->setCellValue([5, $r], empty($f['required']) ? '' : 'Yes');
            $ref->setCellValue([6, $r], implode(' | ', $f['enum'] ?? []));
            $ref->setCellValue([7, $r], $f['description'] ?? '');
            $r++;
        }
        for ($c = 1; $c <= 7; $c++) {
            $ref->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
        }

        $out->setActiveSheetIndex(0);

        $tmpPath = tempnam(sys_get_temp_dir(), 'screen_import_template_') . '.xlsx';
        $writer = IOFactory::createWriter($out, 'Xlsx');
        $writer->save($tmpPath);

        return $tmpPath;
    }
}
