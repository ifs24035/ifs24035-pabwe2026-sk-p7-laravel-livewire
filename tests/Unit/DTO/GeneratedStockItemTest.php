<?php

namespace Tests\Unit\DTO;

use App\Features\Ai\DTO\GeneratedStockItem;
use PHPUnit\Framework\TestCase;

class GeneratedStockItemTest extends TestCase
{
    public function test_dto_properties_and_to_array(): void
    {
        $dto = new GeneratedStockItem(
            name: 'Beras Premium 5 Kg',
            sku: 'BRG-001',
            description: 'Beras kualitas premium',
            quantity: 40,
            unit: 'karung',
            price: 68000.0,
            minStock: 10,
            status: 'available'
        );

        $this->assertEquals('Beras Premium 5 Kg', $dto->name);
        $this->assertEquals('BRG-001', $dto->sku);
        $this->assertEquals('Beras kualitas premium', $dto->description);
        $this->assertEquals(40, $dto->quantity);
        $this->assertEquals('karung', $dto->unit);
        $this->assertEquals(68000.0, $dto->price);
        $this->assertEquals(10, $dto->minStock);
        $this->assertEquals('available', $dto->status);

        $this->assertEquals([
            'name' => 'Beras Premium 5 Kg',
            'sku' => 'BRG-001',
            'description' => 'Beras kualitas premium',
            'quantity' => 40,
            'unit' => 'karung',
            'price' => 68000.0,
            'min_stock' => 10,
            'status' => 'available',
        ], $dto->toArray());
    }

    public function test_dto_default_values(): void
    {
        $dto = new GeneratedStockItem('Barang Minimal');

        $this->assertNull($dto->sku);
        $this->assertNull($dto->description);
        $this->assertSame(0, $dto->quantity);
        $this->assertSame('pcs', $dto->unit);
        $this->assertNull($dto->price);
        $this->assertSame(5, $dto->minStock);
        $this->assertSame('available', $dto->status);
    }
}
