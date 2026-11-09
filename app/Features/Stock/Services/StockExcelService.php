<?php

namespace App\Features\Stock\Services;

use App\Features\Stock\Contracts\StockRepositoryInterface;
use App\Features\Stock\Enums\StockStatus;
use App\Features\Stock\Models\Stock;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockExcelService
{
    public function __construct(
        protected StockRepositoryInterface $stockRepository
    ) {}

    /**
     * Build a Spreadsheet instance containing stock data.
     *
     * Columns: A No | B Name | C SKU | D Description | E Quantity | F Unit | G Price | H Min Stock | I Status | J Created At
     *
     * @param Collection<int, Stock> $stocks
     */
    public function buildSpreadsheet(Collection $stocks, string $sheetTitle = 'Stocks'): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr($sheetTitle, 0, 31));

        $headers = ['No', 'Name', 'SKU', 'Description', 'Quantity', 'Unit', 'Price', 'Min Stock', 'Status', 'Created At'];
        $columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];

        foreach ($headers as $index => $header) {
            $sheet->setCellValue("{$columns[$index]}1", $header);
        }

        // Header Styling
        $headerRange = 'A1:J1';
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2563EB');
        $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $rowNumber = 2;
        foreach ($stocks as $idx => $stock) {
            $statusEnum = StockStatus::tryFrom((string) $stock->status);

            $sheet->setCellValue("A{$rowNumber}", $idx + 1);
            $sheet->setCellValue("B{$rowNumber}", $stock->name);
            $sheet->setCellValue("C{$rowNumber}", $stock->sku ?? '-');
            $sheet->setCellValue("D{$rowNumber}", $stock->description ?? '-');
            $sheet->setCellValue("E{$rowNumber}", (int) $stock->quantity);
            $sheet->setCellValue("F{$rowNumber}", $stock->unit);
            $sheet->setCellValue("G{$rowNumber}", $stock->price !== null ? (float) $stock->price : '-');
            $sheet->setCellValue("H{$rowNumber}", (int) $stock->min_stock);
            $sheet->setCellValue("I{$rowNumber}", $statusEnum ? $statusEnum->label() : (string) $stock->status);
            $sheet->setCellValue("J{$rowNumber}", $stock->created_at ? $stock->created_at->format('Y-m-d H:i') : '-');
            $rowNumber++;
        }

        $lastRow = max(2, $rowNumber - 1);
        $sheet->getStyle("A1:J{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        foreach ($columns as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    /**
     * Export stocks as a downloadable streamed response.
     *
     * @param Collection<int, Stock> $stocks
     */
    public function exportToResponse(Collection $stocks, string $filename = 'stock-barang.xlsx'): StreamedResponse
    {
        $spreadsheet = $this->buildSpreadsheet($stocks);

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Import stocks from an uploaded spreadsheet file path.
     *
     * Expected columns (same as export): A No | B Name | C SKU | D Description | E Quantity | F Unit | G Price | H Min Stock | I Status
     * - Column A is used as the name when column B is empty.
     * - Quantity/min stock are normalised to non-negative integers.
     * - Unit defaults to "pcs", min stock defaults to 5, price "-" / empty becomes null.
     * - An unknown / empty status is derived from quantity vs min stock.
     *
     * @return array{imported: int, errors: array<int, string>}
     */
    public function import(string $filePath, int $userId): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        $imported = 0;
        $errors = [];
        $recordsToInsert = [];
        $now = Carbon::now();

        // Skip header row
        $isFirst = true;

        foreach ($rows as $row) {
            if ($isFirst) {
                $isFirst = false;
                continue;
            }

            $name = isset($row['B']) && trim((string) $row['B']) !== '' ? trim((string) $row['B']) : (isset($row['A']) ? trim((string) $row['A']) : '');

            // Skip empty rows and repeated header rows
            if ($name === '' || strtolower($name) === 'no' || strtolower($name) === 'name') {
                continue;
            }

            $sku = $this->cleanNullable($row['C'] ?? null);
            $description = $this->cleanNullable($row['D'] ?? null);

            $quantity = $this->toNonNegativeInt($row['E'] ?? null, 0);
            $unitRaw = isset($row['F']) ? trim((string) $row['F']) : '';
            $unit = $unitRaw !== '' && $unitRaw !== '-' ? $unitRaw : StockService::DEFAULT_UNIT;
            $price = $this->toPrice($row['G'] ?? null);
            $minStock = $this->toNonNegativeInt($row['H'] ?? null, StockService::DEFAULT_MIN_STOCK);

            $statusRaw = isset($row['I']) ? strtolower(str_replace([' ', '-'], '_', trim((string) $row['I']))) : '';
            $status = in_array($statusRaw, StockStatus::values(), true)
                ? $statusRaw
                : StockStatus::fromQuantity($quantity, $minStock)->value;

            $recordsToInsert[] = [
                'user_id' => $userId,
                'name' => substr($name, 0, 255),
                'sku' => $sku !== null ? substr($sku, 0, 255) : null,
                'description' => $description,
                'quantity' => $quantity,
                'unit' => substr($unit, 0, 255),
                'price' => $price,
                'min_stock' => $minStock,
                'status' => $status,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $imported++;
        }

        if (!empty($recordsToInsert)) {
            $this->stockRepository->insertMany($recordsToInsert);
        }

        return [
            'imported' => $imported,
            'errors' => $errors,
        ];
    }

    /**
     * Normalise a cell into a trimmed string, returning null for empty / "-" values.
     */
    protected function cleanNullable(mixed $value): ?string
    {
        $text = $value === null ? '' : trim((string) $value);

        return $text === '' || $text === '-' ? null : $text;
    }

    /**
     * Convert a cell value into a non-negative integer, falling back to the given default.
     */
    protected function toNonNegativeInt(mixed $value, int $default): int
    {
        $text = $value === null ? '' : trim((string) $value);

        return is_numeric($text) ? max(0, (int) $text) : $default;
    }

    /**
     * Convert a cell value into a non-negative price, or null when not numeric.
     */
    protected function toPrice(mixed $value): ?float
    {
        $text = $value === null ? '' : trim((string) $value);

        return is_numeric($text) ? max(0.0, (float) $text) : null;
    }
}
