<?php

namespace Tests\Feature\Stock;

use App\Features\Ai\Contracts\AiClientInterface;
use App\Features\Ai\DTO\GeneratedStockItem;
use App\Features\Stock\Livewire\StockList;
use App\Features\Stock\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class StockListLivewireTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_stocks(): void
    {
        $this->get('/stocks')->assertRedirect(route('login'));
    }

    public function test_member_can_access_stocks_page(): void
    {
        $user = User::factory()->member()->create();

        $this->actingAs($user)->get('/stocks')->assertOk();
    }

    public function test_member_can_view_own_stocks_only(): void
    {
        $user = User::factory()->member()->create();
        Stock::factory()->create(['user_id' => $user->id, 'name' => 'Barang Spesifik Member']);
        Stock::factory()->lowStock()->create(['user_id' => $user->id, 'name' => 'Barang Menipis']);

        $otherUser = User::factory()->member()->create();
        Stock::factory()->create(['user_id' => $otherUser->id, 'name' => 'Barang Rahasia Orang Lain']);

        $this->actingAs($user);

        Livewire::test(StockList::class)
            ->assertSee('Barang Spesifik Member')
            ->assertSee('Barang Menipis')
            ->assertSee('Low Stock')
            ->assertDontSee('Barang Rahasia Orang Lain');
    }

    public function test_create_stock_validation_and_success(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        // Validation errors
        Livewire::test(StockList::class)
            ->call('openCreateModal')
            ->set('formName', '')
            ->set('formUnit', '')
            ->set('formQuantity', -1)
            ->set('formMinStock', -1)
            ->set('formPrice', -5)
            ->set('formStatus', 'invalid_status')
            ->call('saveStock')
            ->assertHasErrors(['formName', 'formUnit', 'formQuantity', 'formMinStock', 'formPrice', 'formStatus']);

        // Success (status is auto-synced from quantity vs min stock)
        Livewire::test(StockList::class)
            ->call('openCreateModal')
            ->assertSet('showFormModal', true)
            ->set('formName', 'Beras Premium')
            ->set('formSku', 'BRG-001')
            ->set('formDescription', 'Deskripsi **markdown**')
            ->set('formUnit', 'karung')
            ->set('formPrice', '68000')
            ->set('formMinStock', 10)
            ->set('formQuantity', 4)
            ->assertSet('formStatus', 'low_stock')
            ->call('saveStock')
            ->assertHasNoErrors()
            ->assertSet('showFormModal', false);

        $this->assertDatabaseHas('stocks', [
            'user_id' => $user->id,
            'name' => 'Beras Premium',
            'sku' => 'BRG-001',
            'description' => 'Deskripsi **markdown**',
            'quantity' => 4,
            'unit' => 'karung',
            'min_stock' => 10,
            'status' => 'low_stock',
        ]);

        $this->assertEquals('68000.00', Stock::where('name', 'Beras Premium')->first()->price);
    }

    public function test_create_stock_with_empty_optional_fields(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        Livewire::test(StockList::class)
            ->call('openCreateModal')
            ->set('formName', 'Barang Minimal')
            ->set('formSku', '')
            ->set('formPrice', '')
            ->call('saveStock')
            ->assertHasNoErrors();

        $stock = Stock::where('name', 'Barang Minimal')->first();
        $this->assertNotNull($stock);
        $this->assertNull($stock->sku);
        $this->assertNull($stock->price);
        $this->assertEquals(0, $stock->quantity);
        $this->assertEquals('pcs', $stock->unit);
        $this->assertEquals(5, $stock->min_stock);
        $this->assertEquals('out_of_stock', $stock->status);
    }

    public function test_form_status_follows_quantity_and_min_stock(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        Livewire::test(StockList::class)
            ->call('openCreateModal')
            ->assertSet('formQuantity', 0)
            ->assertSet('formMinStock', 5)
            ->assertSet('formStatus', 'out_of_stock')
            ->set('formQuantity', 3)
            ->assertSet('formStatus', 'low_stock')
            ->set('formQuantity', 20)
            ->assertSet('formStatus', 'available')
            ->set('formMinStock', 25)
            ->assertSet('formStatus', 'low_stock')
            ->set('formQuantity', 0)
            ->assertSet('formStatus', 'out_of_stock')
            // Non numeric input leaves the previous status untouched
            ->set('formQuantity', '')
            ->assertSet('formStatus', 'out_of_stock');
    }

    public function test_edit_stock_success(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        $stock = Stock::factory()->create([
            'user_id' => $user->id,
            'name' => 'Nama Lama',
            'sku' => 'OLD-001',
            'quantity' => 50,
            'min_stock' => 5,
            'unit' => 'pcs',
            'status' => 'available',
        ]);

        Livewire::test(StockList::class)
            ->call('openEditModal', $stock->id)
            ->assertSet('editingStockId', $stock->id)
            ->assertSet('formName', 'Nama Lama')
            ->assertSet('formSku', 'OLD-001')
            ->assertSet('formQuantity', 50)
            ->assertSet('formMinStock', 5)
            ->assertSet('formUnit', 'pcs')
            ->assertSet('formStatus', 'available')
            ->set('formName', 'Nama Telah Diedit')
            ->set('formQuantity', 0)
            ->call('saveStock')
            ->assertHasNoErrors()
            ->assertSet('showFormModal', false);

        $this->assertDatabaseHas('stocks', [
            'id' => $stock->id,
            'name' => 'Nama Telah Diedit',
            'quantity' => 0,
            'status' => 'out_of_stock',
        ]);
    }

    public function test_member_cannot_edit_adjust_or_delete_other_members_stock(): void
    {
        $owner = User::factory()->member()->create();
        $other = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $owner->id, 'quantity' => 10]);

        $this->actingAs($other);

        Livewire::test(StockList::class)
            ->call('openEditModal', $stock->id)
            ->assertForbidden();

        Livewire::test(StockList::class)
            ->call('adjustQuantity', $stock->id, 5)
            ->assertForbidden();

        Livewire::test(StockList::class)
            ->call('deleteStock', $stock->id)
            ->assertForbidden();

        $this->assertDatabaseHas('stocks', ['id' => $stock->id, 'quantity' => 10]);
    }

    public function test_adjust_quantity_updates_quantity_and_status(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        $stock = Stock::factory()->create([
            'user_id' => $user->id,
            'quantity' => 6,
            'min_stock' => 5,
            'status' => 'available',
        ]);

        $component = Livewire::test(StockList::class);

        $component->call('adjustQuantity', $stock->id, -1);
        $this->assertEquals(5, $stock->fresh()->quantity);
        $this->assertEquals('low_stock', $stock->fresh()->status);

        $component->call('adjustQuantity', $stock->id, -5);
        $this->assertEquals(0, $stock->fresh()->quantity);
        $this->assertEquals('out_of_stock', $stock->fresh()->status);

        $component->call('adjustQuantity', $stock->id, -1);
        $this->assertEquals(0, $stock->fresh()->quantity);

        $component->call('adjustQuantity', $stock->id, 10);
        $this->assertEquals(10, $stock->fresh()->quantity);
        $this->assertEquals('available', $stock->fresh()->status);
    }

    public function test_delete_stock(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        $stock = Stock::factory()->create(['user_id' => $user->id]);

        Livewire::test(StockList::class)
            ->call('deleteStock', $stock->id);

        $this->assertDatabaseMissing('stocks', ['id' => $stock->id]);
    }

    public function test_confirm_and_cancel_delete_stock(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        $stock = Stock::factory()->create(['user_id' => $user->id]);

        Livewire::test(StockList::class)
            ->assertSet('confirmingStockDeletionId', null)
            ->call('confirmDelete', $stock->id)
            ->assertSet('confirmingStockDeletionId', $stock->id)
            ->call('cancelDelete')
            ->assertSet('confirmingStockDeletionId', null)
            ->call('deleteStock')
            ->assertSet('confirmingStockDeletionId', null)
            ->call('confirmDelete', $stock->id)
            ->call('deleteStock')
            ->assertSet('confirmingStockDeletionId', null);

        $this->assertDatabaseMissing('stocks', ['id' => $stock->id]);
    }

    public function test_search_and_status_filter(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        Stock::factory()->create(['user_id' => $user->id, 'name' => 'Beras Premium', 'sku' => 'BRG-777', 'status' => 'available']);
        Stock::factory()->create(['user_id' => $user->id, 'name' => 'Minyak Goreng', 'sku' => 'MYK-888', 'status' => 'low_stock']);

        Livewire::test(StockList::class)
            ->set('search', 'Minyak')
            ->assertSee('Minyak Goreng')
            ->assertDontSee('Beras Premium')
            ->set('search', 'BRG-777')
            ->assertSee('Beras Premium')
            ->assertDontSee('Minyak Goreng')
            ->set('search', '')
            ->set('statusFilter', 'low_stock')
            ->assertSee('Minyak Goreng')
            ->assertDontSee('Beras Premium');
    }

    public function test_export_excel(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        Stock::factory()->create(['user_id' => $user->id, 'name' => 'Exportable Stock']);

        Livewire::test(StockList::class)
            ->call('exportExcel')
            ->assertFileDownloaded();
    }

    public function test_import_excel(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        foreach (['No', 'Name', 'SKU', 'Description', 'Quantity', 'Unit', 'Price', 'Min Stock', 'Status'] as $i => $header) {
            $sheet->setCellValue(chr(65 + $i).'1', $header);
        }

        $sheet->setCellValue('A2', '1');
        $sheet->setCellValue('B2', 'Imported Excel Stock');
        $sheet->setCellValue('C2', 'XLS-001');
        $sheet->setCellValue('D2', 'Excel Desc');
        $sheet->setCellValue('E2', 3);
        $sheet->setCellValue('F2', 'box');
        $sheet->setCellValue('G2', 12000);
        $sheet->setCellValue('H2', 5);
        $sheet->setCellValue('I2', 'low_stock');

        $tempPath = tempnam(sys_get_temp_dir(), 'test_upload_').'.xlsx';
        (new Xlsx($spreadsheet))->save($tempPath);

        $fileContent = file_get_contents($tempPath);
        @unlink($tempPath);

        $uploadedFile = UploadedFile::fake()->createWithContent('stocks.xlsx', $fileContent);

        Livewire::test(StockList::class)
            ->call('openImportModal')
            ->assertSet('showImportModal', true)
            ->set('importFile', $uploadedFile)
            ->call('importExcel')
            ->assertSet('showImportModal', false);

        $this->assertDatabaseHas('stocks', [
            'user_id' => $user->id,
            'name' => 'Imported Excel Stock',
            'sku' => 'XLS-001',
            'description' => 'Excel Desc',
            'quantity' => 3,
            'unit' => 'box',
            'min_stock' => 5,
            'status' => 'low_stock',
        ]);
    }

    public function test_import_excel_requires_valid_file(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        Livewire::test(StockList::class)
            ->call('openImportModal')
            ->call('importExcel')
            ->assertHasErrors(['importFile']);
    }

    public function test_ai_generation_and_saving(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        // Stub AiClientInterface
        $stubAiClient = $this->createStub(AiClientInterface::class);
        $stubAiClient->method('generateStocks')->willReturn([
            new GeneratedStockItem(
                name: 'AI Stock 1',
                sku: 'AI-001',
                description: 'Desc 1',
                quantity: 2,
                unit: 'box',
                price: 15000.0,
                minStock: 5,
                status: 'low_stock',
            ),
            new GeneratedStockItem(name: 'AI Stock 2'),
        ]);
        $this->app->instance(AiClientInterface::class, $stubAiClient);

        // Test validation for prompt and count
        Livewire::test(StockList::class)
            ->call('openAiModal')
            ->set('aiPrompt', '')
            ->set('aiCount', 0)
            ->call('generateWithAi')
            ->assertHasErrors(['aiPrompt', 'aiCount']);

        $component = Livewire::test(StockList::class)
            ->call('openAiModal')
            ->assertSet('showAiModal', true)
            ->set('aiPrompt', 'Buatkan stok toko kelontong')
            ->set('aiCount', 2)
            ->call('generateWithAi')
            ->assertCount('aiGeneratedItems', 2)
            ->assertCount('aiSelectedIndices', 2);

        // Try saving with no items selected
        $component->set('aiSelectedIndices', [])
            ->call('saveAiGenerated')
            ->assertSet('aiErrorMessage', 'Pilih minimal satu item stok yang ingin disimpan.');

        // Save selected items
        $component->set('aiSelectedIndices', [0, 1])
            ->call('saveAiGenerated')
            ->assertSet('showAiModal', false);

        $this->assertDatabaseHas('stocks', [
            'user_id' => $user->id,
            'name' => 'AI Stock 1',
            'sku' => 'AI-001',
            'quantity' => 2,
            'unit' => 'box',
            'min_stock' => 5,
            'status' => 'low_stock',
        ]);
        $this->assertDatabaseHas('stocks', [
            'user_id' => $user->id,
            'name' => 'AI Stock 2',
            'quantity' => 0,
            'unit' => 'pcs',
            'status' => 'out_of_stock',
        ]);
    }

    public function test_ai_generation_handles_exception(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        $stubAiClient = $this->createStub(AiClientInterface::class);
        $stubAiClient->method('generateStocks')->willThrowException(new \RuntimeException('API quota exceeded'));
        $this->app->instance(AiClientInterface::class, $stubAiClient);

        Livewire::test(StockList::class)
            ->call('openAiModal')
            ->set('aiPrompt', 'Error test')
            ->call('generateWithAi')
            ->assertSet('aiErrorMessage', 'API quota exceeded');
    }

    public function test_close_modals(): void
    {
        $user = User::factory()->member()->create();
        $this->actingAs($user);

        Livewire::test(StockList::class)
            ->call('openCreateModal')
            ->call('closeFormModal')
            ->assertSet('showFormModal', false)
            ->call('openImportModal')
            ->call('closeImportModal')
            ->assertSet('showImportModal', false)
            ->call('openAiModal')
            ->call('closeAiModal')
            ->assertSet('showAiModal', false);
    }
}
