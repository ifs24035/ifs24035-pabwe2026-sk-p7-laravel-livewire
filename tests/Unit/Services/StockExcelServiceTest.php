<?php

namespace Tests\Unit\Services;

use App\Features\Stock\Models\Stock;
use App\Features\Stock\Repositories\StockRepository;
use App\Features\Stock\Services\StockExcelService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class StockExcelServiceTest extends TestCase
{
    use RefreshDatabase;

    protected StockExcelService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StockExcelService(new StockRepository());
    }

    public function test_build_spreadsheet_and_export_response(): void
    {
        $user = User::factory()->create();
        Stock::factory()->create([
            'user_id' => $user->id,
            'name' => 'Beras Export',
            'sku' => 'EXP-001',
            'quantity' => 4,
            'unit' => 'karung',
            'price' => 68000,
            'min_stock' => 5,
            'status' => 'low_stock',
        ]);
        Stock::factory()->create([
            'user_id' => $user->id,
            'name' => 'Tanpa Harga',
            'sku' => null,
            'description' => null,
            'price' => null,
            'status' => 'unknown_status',
        ]);
        $stocks = Stock::query()->where('user_id', $user->id)->orderBy('id')->get();

        $spreadsheet = $this->service->buildSpreadsheet($stocks, 'Custom Stocks');
        $this->assertInstanceOf(Spreadsheet::class, $spreadsheet);

        $sheet = $spreadsheet->getActiveSheet();
        $this->assertEquals('Custom Stocks', $sheet->getTitle());

        // Headers
        $this->assertEquals('Name', $sheet->getCell('B1')->getValue());
        $this->assertEquals('SKU', $sheet->getCell('C1')->getValue());
        $this->assertEquals('Quantity', $sheet->getCell('E1')->getValue());
        $this->assertEquals('Min Stock', $sheet->getCell('H1')->getValue());
        $this->assertEquals('Status', $sheet->getCell('I1')->getValue());

        // First row (complete data)
        $this->assertEquals('Beras Export', $sheet->getCell('B2')->getValue());
        $this->assertEquals('EXP-001', $sheet->getCell('C2')->getValue());
        $this->assertEquals(4, $sheet->getCell('E2')->getValue());
        $this->assertEquals('karung', $sheet->getCell('F2')->getValue());
        $this->assertEquals(68000, $sheet->getCell('G2')->getValue());
        $this->assertEquals('Low Stock', $sheet->getCell('I2')->getValue());

        // Second row (null sku / description / price and unknown status fallbacks)
        $this->assertEquals('-', $sheet->getCell('C3')->getValue());
        $this->assertEquals('-', $sheet->getCell('D3')->getValue());
        $this->assertEquals('-', $sheet->getCell('G3')->getValue());
        $this->assertEquals('unknown_status', $sheet->getCell('I3')->getValue());

        $response = $this->service->exportToResponse($stocks, 'test.xlsx');
        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        ob_start();
        $response->sendContent();
        $outputContent = ob_get_clean();
        $this->assertNotEmpty($outputContent);
    }

    public function test_import_spreadsheet(): void
    {
        $user = User::factory()->create();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Headers
        foreach (['No', 'Name', 'SKU', 'Description', 'Quantity', 'Unit', 'Price', 'Min Stock', 'Status'] as $i => $header) {
            $sheet->setCellValue(chr(65 + $i).'1', $header);
        }

        // Row 2: complete row with explicit valid status
        $sheet->setCellValue('A2', '1');
        $sheet->setCellValue('B2', 'Imported Stock 1');
        $sheet->setCellValue('C2', 'IMP-001');
        $sheet->setCellValue('D2', 'Desc 1');
        $sheet->setCellValue('E2', 20);
        $sheet->setCellValue('F2', 'box');
        $sheet->setCellValue('G2', 15000.5);
        $sheet->setCellValue('H2', 10);
        $sheet->setCellValue('I2', 'available');

        // Row 3: '-' sku/description, negative quantity, empty unit, '-' price, empty min stock, unknown status
        $sheet->setCellValue('A3', '2');
        $sheet->setCellValue('B3', 'Imported Stock 2');
        $sheet->setCellValue('C3', '-');
        $sheet->setCellValue('D3', '-');
        $sheet->setCellValue('E3', '-5');
        $sheet->setCellValue('F3', '');
        $sheet->setCellValue('G3', '-');
        $sheet->setCellValue('H3', '');
        $sheet->setCellValue('I3', 'unknown_status');

        // Row 4: column B empty => fallback to column A, human readable status "Low Stock", '-' unit
        $sheet->setCellValue('A4', 'Stock from Col A');
        $sheet->setCellValue('B4', '');
        $sheet->setCellValue('C4', 'COL-A');
        $sheet->setCellValue('D4', 'Col A Desc');
        $sheet->setCellValue('E4', 3);
        $sheet->setCellValue('F4', '-');
        $sheet->setCellValue('H4', 5);
        $sheet->setCellValue('I4', 'Low Stock');

        // Row 5: repeated header row (ignored)
        $sheet->setCellValue('A5', 'No');
        $sheet->setCellValue('B5', 'Name');
        $sheet->setCellValue('C5', 'Skip this');

        // Row 6: empty row (ignored)
        $sheet->setCellValue('A6', '');
        $sheet->setCellValue('B6', '');

        $tempPath = tempnam(sys_get_temp_dir(), 'test_excel_').'.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        $result = $this->service->import($tempPath, $user->id);
        @unlink($tempPath);

        $this->assertEquals(3, $result['imported']);
        $this->assertSame([], $result['errors']);

        $first = Stock::where('name', 'Imported Stock 1')->first();
        $this->assertNotNull($first);
        $this->assertEquals($user->id, $first->user_id);
        $this->assertEquals('IMP-001', $first->sku);
        $this->assertEquals('Desc 1', $first->description);
        $this->assertEquals(20, $first->quantity);
        $this->assertEquals('box', $first->unit);
        $this->assertEquals('15000.50', $first->price);
        $this->assertEquals(10, $first->min_stock);
        $this->assertEquals('available', $first->status);

        $second = Stock::where('name', 'Imported Stock 2')->first();
        $this->assertNotNull($second);
        $this->assertNull($second->sku);
        $this->assertNull($second->description);
        $this->assertEquals(0, $second->quantity);
        $this->assertEquals('pcs', $second->unit);
        $this->assertNull($second->price);
        $this->assertEquals(5, $second->min_stock);
        $this->assertEquals('out_of_stock', $second->status);

        $third = Stock::where('name', 'Stock from Col A')->first();
        $this->assertNotNull($third);
        $this->assertEquals('COL-A', $third->sku);
        $this->assertEquals(3, $third->quantity);
        $this->assertEquals('pcs', $third->unit);
        $this->assertEquals('low_stock', $third->status);

        $this->assertDatabaseCount('stocks', 3);
    }

    public function test_import_spreadsheet_without_data_rows_inserts_nothing(): void
    {
        $user = User::factory()->create();

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->setCellValue('B1', 'Name');

        $tempPath = tempnam(sys_get_temp_dir(), 'test_excel_').'.xlsx';
        (new Xlsx($spreadsheet))->save($tempPath);

        $result = $this->service->import($tempPath, $user->id);
        @unlink($tempPath);

        $this->assertEquals(0, $result['imported']);
        $this->assertDatabaseCount('stocks', 0);
    }
}
