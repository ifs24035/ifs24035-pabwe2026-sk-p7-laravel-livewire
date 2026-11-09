<?php

namespace Tests\Feature\Stock;

use App\Features\Stock\Livewire\StockDetail;
use App\Features\Stock\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class StockDetailLivewireTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_stock_detail(): void
    {
        $stock = Stock::factory()->create();

        $this->get(route('stocks.show', $stock->id))
            ->assertRedirect(route('login'));
    }

    public function test_member_cannot_access_other_member_stock(): void
    {
        $owner = User::factory()->member()->create();
        $otherMember = User::factory()->member()->create();

        $stock = Stock::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($otherMember);

        $this->get(route('stocks.show', $stock->id))
            ->assertStatus(403);
    }

    public function test_member_can_access_own_stock_page(): void
    {
        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id, 'name' => 'Halaman Detail Saya']);

        $this->actingAs($user)
            ->get(route('stocks.show', $stock->id))
            ->assertOk()
            ->assertSee('Halaman Detail Saya');
    }

    public function test_member_can_view_own_stock_detail(): void
    {
        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create([
            'user_id' => $user->id,
            'name' => 'Beras Utama Pro',
            'sku' => 'BRG-001',
            'description' => '# Deskripsi Markdown',
            'quantity' => 40,
            'unit' => 'karung',
            'min_stock' => 10,
            'status' => 'available',
        ]);

        $this->actingAs($user);

        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->assertOk()
            ->assertSet('name', 'Beras Utama Pro')
            ->assertSet('sku', 'BRG-001')
            ->assertSet('description', '# Deskripsi Markdown')
            ->assertSet('quantity', 40)
            ->assertSet('unit', 'karung')
            ->assertSet('minStock', 10)
            ->assertSet('status', 'available')
            ->assertSee('Beras Utama Pro')
            ->assertSee('BRG-001');
    }

    public function test_detail_shows_low_and_out_of_stock_warnings(): void
    {
        $user = User::factory()->member()->create();
        $low = Stock::factory()->lowStock()->create(['user_id' => $user->id]);
        $out = Stock::factory()->outOfStock()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        Livewire::test(StockDetail::class, ['stock' => $low])->assertSee('menipis');
        Livewire::test(StockDetail::class, ['stock' => $out])->assertSee('habis');
    }

    public function test_open_and_close_edit_modal(): void
    {
        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create([
            'user_id' => $user->id,
            'name' => 'Nama Detail',
            'description' => 'Deskripsi lama',
        ]);

        $this->actingAs($user);

        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->assertSet('showEditModal', false)
            ->call('openEditModal')
            ->assertSet('showEditModal', true)
            ->assertSet('name', 'Nama Detail')
            ->assertSet('description', 'Deskripsi lama')
            ->call('closeEditModal')
            ->assertSet('showEditModal', false);
    }

    public function test_update_stock_validation_and_success(): void
    {
        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id, 'name' => 'Nama Awal', 'quantity' => 50, 'min_stock' => 5]);

        $this->actingAs($user);

        // Validation errors
        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->call('openEditModal')
            ->set('name', 'ab') // min:3
            ->set('unit', '')
            ->set('quantity', -1)
            ->set('minStock', -1)
            ->set('price', -10)
            ->set('status', 'invalid_status')
            ->call('saveStock')
            ->assertHasErrors(['name', 'unit', 'quantity', 'minStock', 'price', 'status']);

        // Success
        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->call('openEditModal')
            ->set('name', 'Nama Telah Diperbarui')
            ->set('sku', 'UPD-001')
            ->set('description', '## Rincian Baru')
            ->set('unit', 'box')
            ->set('price', '25000')
            ->set('minStock', 10)
            ->set('quantity', 7) // 7 <= 10 => low_stock (auto-synced)
            ->assertSet('status', 'low_stock')
            ->call('saveStock')
            ->assertHasNoErrors()
            ->assertSet('showEditModal', false);

        $this->assertDatabaseHas('stocks', [
            'id' => $stock->id,
            'name' => 'Nama Telah Diperbarui',
            'sku' => 'UPD-001',
            'description' => '## Rincian Baru',
            'unit' => 'box',
            'quantity' => 7,
            'min_stock' => 10,
            'status' => 'low_stock',
        ]);
        $this->assertEquals('25000.00', $stock->fresh()->price);
    }

    public function test_update_stock_with_empty_optional_fields(): void
    {
        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id, 'sku' => 'HAS-SKU']);

        $this->actingAs($user);

        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->call('openEditModal')
            ->set('sku', '')
            ->set('price', '')
            ->call('saveStock')
            ->assertHasNoErrors();

        $fresh = $stock->fresh();
        $this->assertNull($fresh->sku);
        $this->assertNull($fresh->price);
    }

    public function test_edit_status_follows_quantity_and_min_stock(): void
    {
        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id, 'quantity' => 50, 'min_stock' => 5]);

        $this->actingAs($user);

        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->call('openEditModal')
            ->set('quantity', 0)
            ->assertSet('status', 'out_of_stock')
            ->set('quantity', 3)
            ->assertSet('status', 'low_stock')
            ->set('minStock', 2)
            ->assertSet('status', 'available')
            // Non numeric input leaves the previous status untouched
            ->set('quantity', '')
            ->assertSet('status', 'available');
    }

    public function test_adjust_quantity_syncs_status(): void
    {
        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id, 'quantity' => 6, 'min_stock' => 5, 'status' => 'available']);

        $this->actingAs($user);

        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->call('adjustQuantity', -1)
            ->assertSet('quantity', 5)
            ->assertSet('status', 'low_stock')
            ->call('adjustQuantity', -10)
            ->assertSet('quantity', 0)
            ->assertSet('status', 'out_of_stock')
            ->call('adjustQuantity', 10)
            ->assertSet('quantity', 10)
            ->assertSet('status', 'available');

        $this->assertDatabaseHas('stocks', [
            'id' => $stock->id,
            'quantity' => 10,
            'status' => 'available',
        ]);
    }

    public function test_admin_can_view_but_not_modify_member_stock(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $member->id, 'name' => 'Stok Milik Member', 'quantity' => 10]);

        $this->actingAs($admin);

        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->assertOk()
            ->assertSee('Stok Milik Member');

        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->call('adjustQuantity', 1)
            ->assertForbidden();

        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->call('openEditModal')
            ->assertForbidden();

        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->call('deleteStock')
            ->assertForbidden();

        $this->assertDatabaseHas('stocks', ['id' => $stock->id, 'quantity' => 10]);
    }

    public function test_cover_photo_upload_and_delete(): void
    {
        Storage::fake('public');

        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id, 'cover_image_path' => null]);

        $this->actingAs($user);

        // Invalid file
        $invalidFile = UploadedFile::fake()->create('document.pdf', 100);
        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->set('coverPhoto', $invalidFile)
            ->assertHasErrors(['coverPhoto']);

        // Valid image upload
        $imageFile = UploadedFile::fake()->image('cover.jpg', 800, 400);
        $component = Livewire::test(StockDetail::class, ['stock' => $stock])
            ->set('coverPhoto', $imageFile)
            ->assertHasNoErrors();

        $stock->refresh();
        $this->assertNotNull($stock->cover_image_path);
        Storage::disk('public')->assertExists($stock->cover_image_path);

        // Delete cover photo
        $component->call('deleteCoverPhoto');

        $stock->refresh();
        $this->assertNull($stock->cover_image_path);
    }

    public function test_delete_stock_redirects(): void
    {
        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->call('deleteStock')
            ->assertRedirect(route('stocks.index'));

        $this->assertDatabaseMissing('stocks', ['id' => $stock->id]);
    }

    public function test_confirm_and_cancel_delete_modal_actions(): void
    {
        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        Livewire::test(StockDetail::class, ['stock' => $stock])
            ->assertSet('confirmingStockDeletion', false)
            ->call('confirmDeleteStock')
            ->assertSet('confirmingStockDeletion', true)
            ->call('cancelDeleteStock')
            ->assertSet('confirmingStockDeletion', false)
            ->assertSet('confirmingCoverDeletion', false)
            ->call('confirmDeleteCover')
            ->assertSet('confirmingCoverDeletion', true)
            ->call('cancelDeleteCover')
            ->assertSet('confirmingCoverDeletion', false);
    }
}
