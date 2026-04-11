<?php

namespace App\Console\Commands;

use App\Models\VenueCategory;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class GenerateImportTemplate extends Command
{
    protected $signature = 'oohx:import-template {--output=storage/app/public/templates/oohx-import-template.xlsx}';
    protected $description = 'Generate OOHX Screen import Excel template';

    private const BRAND_COLOR = 'E5007D';
    private const HEADER_BG   = 'F5F5F7';
    private const REQUIRED_BG = 'FFF3CD';
    private const BORDER_CLR  = 'D1D5DB';

    public function handle(): void
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('OOHX.net')
            ->setTitle('OOHX Screen Import Template')
            ->setDescription('Template để import screens hàng loạt vào OOHX marketplace');

        $this->buildSitesSheet($spreadsheet);
        $this->buildScreensSheet($spreadsheet);
        $this->buildGuideSheet($spreadsheet);

        // Set Sites as active sheet
        $spreadsheet->setActiveSheetIndex(0);

        $output = $this->option('output');
        $dir = dirname($output);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($output);

        $this->info("Template created: {$output}");
    }

    // ── Sheet 1: Sites ───────────────────────────────────────────────────────

    private function buildSitesSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sites');

        // Title row
        $sheet->setCellValue('A1', 'OOHX — Danh sách Site (Vị trí đặt biển)');
        $sheet->mergeCells('A1:G1');
        $this->styleTitle($sheet, 'A1:G1');

        // Subtitle
        $sheet->setCellValue('A2', 'Mỗi site là 1 địa điểm vật lý. Nhiều screens có thể thuộc cùng 1 site.');
        $sheet->mergeCells('A2:G2');
        $sheet->getStyle('A2')->getFont()->setSize(10)->setColor(new Color('666666'));

        // Headers (row 4)
        $headers = [
            'A4' => ['Mã site *', 'Mã định danh duy nhất cho site, ví dụ: HN-SITE-001'],
            'B4' => ['Tên site *', 'Tên hiển thị, ví dụ: Ngã tư Láng Hạ – Lê Văn Lương'],
            'C4' => ['Địa chỉ', 'Địa chỉ chi tiết, ví dụ: 123 Láng Hạ, Đống Đa'],
            'D4' => ['Thành phố', 'Tên tỉnh/thành, ví dụ: Hà Nội'],
            'E4' => ['Latitude', 'Vĩ độ (VD: 21.0285)'],
            'F4' => ['Longitude', 'Kinh độ (VD: 105.8542)'],
            'G4' => ['Mô tả', 'Ghi chú thêm về vị trí'],
        ];

        foreach ($headers as $cell => [$label, $comment]) {
            $sheet->setCellValue($cell, $label);
            $sheet->getComment($cell)->getText()->createTextRun($comment)->getFont()->setSize(9);
        }
        $this->styleHeaderRow($sheet, 'A4:G4');

        // Sample data
        $samples = [
            ['HN-SITE-001', 'Ngã tư Láng Hạ – Lê Văn Lương', '123 Láng Hạ, Đống Đa', 'Hà Nội', 21.0127, 105.8118, 'Giao lộ chính, traffic cao'],
            ['HN-SITE-002', 'Vincom Bà Triệu', '191 Bà Triệu, Hai Bà Trưng', 'Hà Nội', 21.0115, 105.8500, 'TTTM Vincom Center'],
            ['HCM-SITE-001', 'Nguyễn Huệ Walking Street', 'Phố đi bộ Nguyễn Huệ, Q1', 'Hồ Chí Minh', 10.7740, 106.7020, 'Khu vực du lịch trung tâm'],
        ];
        foreach ($samples as $i => $row) {
            $r = $i + 5;
            $sheet->fromArray($row, null, "A{$r}");
            $sheet->getStyle("A{$r}:G{$r}")->getFont()->setColor(new Color('999999'))->setItalic(true);
        }

        // Column widths
        $widths = ['A' => 16, 'B' => 35, 'C' => 35, 'D' => 16, 'E' => 14, 'F' => 14, 'G' => 30];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        // Mark required columns
        $sheet->getStyle('A4:B4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::REQUIRED_BG);
    }

    // ── Sheet 2: Screens ─────────────────────────────────────────────────────

    private function buildScreensSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Screens');

        // Title
        $sheet->setCellValue('A1', 'OOHX — Danh sách Screens (Màn hình quảng cáo)');
        $sheet->mergeCells('A1:N1');
        $this->styleTitle($sheet, 'A1:N1');

        $sheet->setCellValue('A2', 'Mỗi screen là 1 đơn vị màn hình. Cột có dấu * là bắt buộc. Xoá các dòng mẫu trước khi import.');
        $sheet->mergeCells('A2:N2');
        $sheet->getStyle('A2')->getFont()->setSize(10)->setColor(new Color('666666'));

        // Group headers (row 3)
        $groups = [
            ['A3:C3', 'Thông tin cơ bản'],
            ['D3:E3', 'Vị trí'],
            ['F3:I3', 'Kỹ thuật màn hình'],
            ['J3:L3', 'Giá & Inventory'],
            ['M3:N3', 'Lịch hoạt động'],
        ];
        foreach ($groups as [$range, $label]) {
            $sheet->mergeCells($range);
            $sheet->setCellValue(explode(':', $range)[0], $label);
        }
        $sheet->getStyle('A3:N3')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::BRAND_COLOR]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Column headers (row 4)
        $headers = [
            'A4' => ['Mã screen *', 'ID duy nhất, VD: HN-SCR-001'],
            'B4' => ['Tên screen *', 'Tên hiển thị trên marketplace'],
            'C4' => ['Mã site *', 'Phải trùng với Mã site ở sheet Sites'],
            'D4' => ['Loại biển', 'Chọn từ danh sách 12 loại VN DOOH'],
            'E4' => ['Network', 'Tên network, tự tạo nếu chưa có'],
            'F4' => ['Rộng (px) *', 'Pixel width, VD: 1920'],
            'G4' => ['Cao (px) *', 'Pixel height, VD: 1080'],
            'H4' => ['Rộng (cm)', 'Kích thước vật lý'],
            'I4' => ['Cao (cm)', 'Kích thước vật lý'],
            'J4' => ['Giá (VND/tháng)', 'Giá sàn hiển thị trên marketplace'],
            'K4' => ['Lượt xem/tuần', 'Ước tính weekly impressions'],
            'L4' => ['Thời lượng QC', 'Giây, mặc định 15'],
            'M4' => ['Giờ mở cửa', 'Format: HH:MM, VD: 08:00'],
            'N4' => ['Giờ đóng cửa', 'Format: HH:MM, VD: 22:00'],
        ];

        foreach ($headers as $cell => [$label, $comment]) {
            $sheet->setCellValue($cell, $label);
            $sheet->getComment($cell)->getText()->createTextRun($comment)->getFont()->setSize(9);
        }
        $this->styleHeaderRow($sheet, 'A4:N4');

        // Mark required
        foreach (['A4', 'B4', 'C4', 'F4', 'G4'] as $cell) {
            $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::REQUIRED_BG);
        }

        // Venue type dropdown validation
        $venueOptions = $this->getVenueOptions();
        if ($venueOptions) {
            for ($r = 5; $r <= 1004; $r++) {
                $validation = $sheet->getCell("D{$r}")->getDataValidation();
                $validation->setType(DataValidation::TYPE_LIST);
                $validation->setFormula1('"' . $venueOptions . '"');
                $validation->setShowDropDown(true);
                $validation->setShowErrorMessage(true);
                $validation->setErrorTitle('Loại biển không hợp lệ');
                $validation->setError('Vui lòng chọn từ danh sách.');
            }
        }

        // Sample data
        $samples = [
            ['HN-SCR-001', 'LED Láng Hạ 5x3m', 'HN-SITE-001', 'Ngoài trời', 'Hanoiads Network', 1920, 1080, 500, 300, 15000000, 50000, 15, '08:00', '22:00'],
            ['HN-SCR-002', 'LCD Vincom Lobby', 'HN-SITE-002', 'Trung tâm thương mại', 'Hanoiads Network', 1080, 1920, 60, 107, 5000000, 20000, 10, '09:00', '21:00'],
            ['HCM-SCR-001', 'LED Nguyễn Huệ Billboard', 'HCM-SITE-001', 'Ngoài trời', 'HCMC Media', 3840, 2160, 800, 450, 30000000, 120000, 15, '00:00', '24:00'],
        ];
        foreach ($samples as $i => $row) {
            $r = $i + 5;
            $sheet->fromArray($row, null, "A{$r}");
            $sheet->getStyle("A{$r}:N{$r}")->getFont()->setColor(new Color('999999'))->setItalic(true);
        }

        // Column widths
        $widths = ['A' => 16, 'B' => 28, 'C' => 16, 'D' => 22, 'E' => 20, 'F' => 12, 'G' => 12, 'H' => 12, 'I' => 12, 'J' => 18, 'K' => 16, 'L' => 14, 'M' => 14, 'N' => 14];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }
    }

    // ── Sheet 3: Hướng dẫn ───────────────────────────────────────────────────

    private function buildGuideSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Hướng dẫn');

        $guide = [
            ['HƯỚNG DẪN IMPORT SCREENS VÀO OOHX.NET'],
            [''],
            ['Bước 1:', 'Điền thông tin Sites vào sheet "Sites" — mỗi dòng là 1 địa điểm vật lý'],
            ['Bước 2:', 'Điền thông tin Screens vào sheet "Screens" — mỗi dòng là 1 màn hình'],
            ['Bước 3:', 'Đảm bảo cột "Mã site" ở sheet Screens trùng với cột "Mã site" ở sheet Sites'],
            ['Bước 4:', 'Xoá các dòng mẫu (in nghiêng) trước khi import'],
            ['Bước 5:', 'Vào Publisher Panel → Tools → Import Sites → Upload file này'],
            [''],
            ['QUY TẮC:'],
            ['• Cột có dấu * là bắt buộc, không được để trống'],
            ['• Mã site và Mã screen phải duy nhất trong file'],
            ['• Nếu site/screen đã tồn tại (trùng mã), hệ thống sẽ cập nhật thay vì tạo mới'],
            ['• Latitude: -90 đến 90 | Longitude: -180 đến 180'],
            ['• Giá VND/tháng: nhập số nguyên, VD: 15000000 (15 triệu)'],
            ['• Giờ hoạt động: format HH:MM, VD: 08:00. Để trống = 24/7'],
            [''],
            ['LOẠI BIỂN (12 danh mục VN DOOH):'],
        ];

        foreach ($guide as $i => $row) {
            $r = $i + 1;
            if (count($row) === 1) {
                $sheet->setCellValue("A{$r}", $row[0]);
                $sheet->mergeCells("A{$r}:D{$r}");
            } else {
                $sheet->setCellValue("A{$r}", $row[0]);
                $sheet->setCellValue("B{$r}", $row[1]);
            }
        }

        // Title style
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB(self::BRAND_COLOR);
        $sheet->getStyle('A9')->getFont()->setBold(true)->setSize(11);

        // Venue categories list
        $categories = VenueCategory::where('is_active', true)->orderBy('sort_order')->get();
        $catRow = count($guide) + 1;
        $sheet->getStyle("A{$catRow}")->getFont()->setBold(true)->setSize(11);

        foreach ($categories as $cat) {
            $catRow++;
            $sheet->setCellValue("A{$catRow}", $cat->name_vi);
            $sheet->setCellValue("B{$catRow}", $cat->name);
            $sheet->setCellValue("C{$catRow}", $cat->description ?? '');
        }

        $sheet->getColumnDimension('A')->setWidth(25);
        $sheet->getColumnDimension('B')->setWidth(40);
        $sheet->getColumnDimension('C')->setWidth(50);
        $sheet->getColumnDimension('D')->setWidth(40);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function styleTitle($sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => self::BRAND_COLOR]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(36);
    }

    private function styleHeaderRow($sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font'      => ['bold' => true, 'size' => 11],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::HEADER_BG]],
            'borders'   => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::BORDER_CLR]]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension((int) substr($range, strpos($range, ':') - 1, 1))->setRowHeight(32);
    }

    private function getVenueOptions(): string
    {
        try {
            return VenueCategory::where('is_active', true)
                ->orderBy('sort_order')
                ->pluck('name_vi')
                ->implode(',');
        } catch (\Exception $e) {
            return '';
        }
    }
}
