<?php

namespace Tests\Unit\Services;

use App\Features\Stock\Contracts\StockRepositoryInterface;
use App\Features\Stock\Enums\StockStatus;
use App\Features\Stock\Models\Stock;
use App\Features\Stock\Repositories\StockRepository;
use App\Features\Stock\Services\StockService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    protected StockService $service;
    protected StockRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new StockRepository();
        $this->service = new StockService($this->repository);
    }

    public function test_get_stocks_for_user(): void
    {
        $user = User::factory()->create();
        Stock::factory()->count(2)->create(['user_id' => $user->id]);

        $paginator = $this->service->getStocksForUser($user->id);
        $this->assertCount(2, $paginator);
    }

    public function test_create_stock_with_explicit_values(): void
    {
        $user = User::factory()->create();
        $stock = $this->service->createStock($user->id, [
            'name' => 'Service Stock',
            'sku' => 'SRV-001',
            'description' => 'Desc',
            'quantity' => 20,
            'unit' => 'box',
            'price' => 12500,
            'min_stock' => 5,
        ]);

        $this->assertInstanceOf(Stock::class, $stock);
        $this->assertEquals('Service Stock', $stock->name);
        $this->assertEquals('SRV-001', $stock->sku);
        $this->assertEquals('box', $stock->unit);
        $this->assertEquals(20, $stock->quantity);
        $this->assertEquals(StockStatus::AVAILABLE->value, $stock->status);
    }

    public function test_create_stock_derives_status_from_quantity(): void
    {
        $user = User::factory()->create();

        $low = $this->service->createStock($user->id, ['name' => 'Low', 'quantity' => 3, 'min_stock' => 5]);
        $this->assertEquals(StockStatus::LOW_STOCK->value, $low->status);

        $out = $this->service->createStock($user->id, ['name' => 'Out', 'quantity' => 0, 'min_stock' => 5]);
        $this->assertEquals(StockStatus::OUT_OF_STOCK->value, $out->status);

        $ok = $this->service->createStock($user->id, ['name' => 'Ok', 'quantity' => 10, 'min_stock' => 5]);
        $this->assertEquals(StockStatus::AVAILABLE->value, $ok->status);
    }

    public function test_create_stock_applies_defaults_and_clamps_negative_values(): void
    {
        $user = User::factory()->create();

        $stock = $this->service->createStock($user->id, ['name' => 'Minimal']);
        $this->assertEquals(0, $stock->quantity);
        $this->assertEquals('pcs', $stock->unit);
        $this->assertEquals(5, $stock->min_stock);
        $this->assertNull($stock->sku);
        $this->assertNull($stock->price);
        $this->assertEquals(StockStatus::OUT_OF_STOCK->value, $stock->status);

        $clamped = $this->service->createStock($user->id, ['name' => 'Negative', 'quantity' => -9, 'min_stock' => -2]);
        $this->assertEquals(0, $clamped->quantity);
        $this->assertEquals(0, $clamped->min_stock);
    }

    public function test_create_stock_honors_explicit_status(): void
    {
        $user = User::factory()->create();

        $stock = $this->service->createStock($user->id, [
            'name' => 'Manual Status',
            'quantity' => 50,
            'min_stock' => 5,
            'status' => StockStatus::LOW_STOCK->value,
        ]);

        $this->assertEquals(StockStatus::LOW_STOCK->value, $stock->status);
    }

    public function test_update_stock_fields(): void
    {
        $user = User::factory()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id, 'name' => 'Old Name']);

        $updated = $this->service->updateStock($stock, [
            'name' => 'New Name',
            'sku' => 'NEW-001',
            'description' => 'Updated Desc',
            'unit' => 'kg',
            'price' => 9900,
        ]);

        $this->assertTrue($updated);
        $stock->refresh();
        $this->assertEquals('New Name', $stock->name);
        $this->assertEquals('NEW-001', $stock->sku);
        $this->assertEquals('Updated Desc', $stock->description);
        $this->assertEquals('kg', $stock->unit);
        $this->assertEquals('9900.00', $stock->price);
    }

    public function test_update_stock_rederives_status_when_quantity_or_min_stock_changes(): void
    {
        $user = User::factory()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id, 'quantity' => 50, 'min_stock' => 5, 'status' => 'available']);

        $this->service->updateStock($stock, ['quantity' => 2]);
        $stock->refresh();
        $this->assertEquals(2, $stock->quantity);
        $this->assertEquals(StockStatus::LOW_STOCK->value, $stock->status);

        $this->service->updateStock($stock, ['quantity' => 0]);
        $stock->refresh();
        $this->assertEquals(StockStatus::OUT_OF_STOCK->value, $stock->status);

        // Quantity equal to min_stock is still "low_stock" (threshold is inclusive)
        $this->service->updateStock($stock, ['quantity' => 5]);
        $stock->refresh();
        $this->assertEquals(StockStatus::LOW_STOCK->value, $stock->status);

        // Quantity above min_stock is "available"
        $this->service->updateStock($stock, ['quantity' => 8]);
        $stock->refresh();
        $this->assertEquals(StockStatus::AVAILABLE->value, $stock->status);

        // Raising min_stock above the current quantity makes it "low_stock" again
        $this->service->updateStock($stock, ['min_stock' => 10]);
        $stock->refresh();
        $this->assertEquals(StockStatus::LOW_STOCK->value, $stock->status);

        $this->service->updateStock($stock, ['min_stock' => 3]);
        $stock->refresh();
        $this->assertEquals(3, $stock->min_stock);
        $this->assertEquals(StockStatus::AVAILABLE->value, $stock->status);
    }

    public function test_update_stock_preserves_status_when_quantity_untouched(): void
    {
        $user = User::factory()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id, 'quantity' => 50, 'min_stock' => 5, 'status' => 'low_stock']);

        $this->service->updateStock($stock, ['name' => 'Rename Only']);
        $stock->refresh();

        $this->assertEquals('Rename Only', $stock->name);
        $this->assertEquals('low_stock', $stock->status);
    }

    public function test_update_stock_honors_explicit_status(): void
    {
        $user = User::factory()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id, 'quantity' => 50, 'min_stock' => 5]);

        $this->service->updateStock($stock, ['quantity' => 50, 'status' => 'out_of_stock']);
        $stock->refresh();

        $this->assertEquals('out_of_stock', $stock->status);
    }

    public function test_adjust_quantity_syncs_status(): void
    {
        $user = User::factory()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id, 'quantity' => 6, 'min_stock' => 5, 'status' => 'available']);

        // 6 -> 5 : within min stock => low_stock
        $this->assertTrue($this->service->adjustQuantity($stock, -1));
        $stock->refresh();
        $this->assertEquals(5, $stock->quantity);
        $this->assertEquals(StockStatus::LOW_STOCK->value, $stock->status);

        // 5 -> 0 : out_of_stock
        $this->service->adjustQuantity($stock, -5);
        $stock->refresh();
        $this->assertEquals(0, $stock->quantity);
        $this->assertEquals(StockStatus::OUT_OF_STOCK->value, $stock->status);

        // Quantity never drops below zero
        $this->service->adjustQuantity($stock, -3);
        $stock->refresh();
        $this->assertEquals(0, $stock->quantity);
        $this->assertEquals(StockStatus::OUT_OF_STOCK->value, $stock->status);

        // 0 -> 10 : available
        $this->service->adjustQuantity($stock, 10);
        $stock->refresh();
        $this->assertEquals(10, $stock->quantity);
        $this->assertEquals(StockStatus::AVAILABLE->value, $stock->status);
    }

    public function test_delete_stock(): void
    {
        $user = User::factory()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id]);

        $deleted = $this->service->deleteStock($stock);
        $this->assertTrue($deleted);
        $this->assertDatabaseMissing('stocks', ['id' => $stock->id]);
    }

    public function test_cover_photo_management(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id, 'status' => 'low_stock', 'quantity' => 50]);

        $fakeFile = UploadedFile::fake()->image('cover.jpg');

        // Update cover (status must not be touched)
        $path = $this->service->updateCoverPhoto($stock, $fakeFile);
        $stock->refresh();
        $this->assertEquals($path, $stock->cover_image_path);
        $this->assertEquals('low_stock', $stock->status);
        $this->assertStringStartsWith('stock-covers/', $path);
        Storage::disk('public')->assertExists($path);

        // Update cover again (replaces old)
        $newFakeFile = UploadedFile::fake()->image('new-cover.jpg');
        $newPath = $this->service->updateCoverPhoto($stock, $newFakeFile);
        $stock->refresh();
        $this->assertEquals($newPath, $stock->cover_image_path);
        Storage::disk('public')->assertMissing($path);
        Storage::disk('public')->assertExists($newPath);

        // Delete cover
        $deleted = $this->service->deleteCoverPhoto($stock);
        $this->assertTrue($deleted);
        $stock->refresh();
        $this->assertNull($stock->cover_image_path);
        Storage::disk('public')->assertMissing($newPath);

        // Delete stock cleans up cover photo if present
        $anotherFile = UploadedFile::fake()->image('another.jpg');
        $anotherPath = $this->service->updateCoverPhoto($stock, $anotherFile);
        Storage::disk('public')->assertExists($anotherPath);

        $this->service->deleteStock($stock);
        Storage::disk('public')->assertMissing($anotherPath);
    }
}
