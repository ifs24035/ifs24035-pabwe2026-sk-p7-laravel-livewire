<?php

namespace Tests\Unit\Enums;

use App\Features\Stock\Enums\StockStatus;
use PHPUnit\Framework\TestCase;

class StockStatusTest extends TestCase
{
    public function test_stock_status_labels(): void
    {
        $this->assertEquals('Available', StockStatus::AVAILABLE->label());
        $this->assertEquals('Low Stock', StockStatus::LOW_STOCK->label());
        $this->assertEquals('Out of Stock', StockStatus::OUT_OF_STOCK->label());
    }

    public function test_stock_status_badge_classes(): void
    {
        $this->assertStringContainsString('emerald', StockStatus::AVAILABLE->badgeClass());
        $this->assertStringContainsString('amber', StockStatus::LOW_STOCK->badgeClass());
        $this->assertStringContainsString('rose', StockStatus::OUT_OF_STOCK->badgeClass());
    }

    public function test_from_quantity_resolves_status(): void
    {
        // Out of stock when quantity is zero (or below)
        $this->assertSame(StockStatus::OUT_OF_STOCK, StockStatus::fromQuantity(0, 5));
        $this->assertSame(StockStatus::OUT_OF_STOCK, StockStatus::fromQuantity(-3, 5));
        $this->assertSame(StockStatus::OUT_OF_STOCK, StockStatus::fromQuantity(0, 0));

        // Low stock when quantity is within the min stock threshold (inclusive)
        $this->assertSame(StockStatus::LOW_STOCK, StockStatus::fromQuantity(3, 5));
        $this->assertSame(StockStatus::LOW_STOCK, StockStatus::fromQuantity(5, 5));

        // Available when quantity is above the threshold
        $this->assertSame(StockStatus::AVAILABLE, StockStatus::fromQuantity(6, 5));
        $this->assertSame(StockStatus::AVAILABLE, StockStatus::fromQuantity(1, 0));
    }

    public function test_values_lists_all_status_values(): void
    {
        $this->assertSame(['available', 'low_stock', 'out_of_stock'], StockStatus::values());
    }
}
