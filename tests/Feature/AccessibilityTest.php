<?php

namespace Tests\Feature;

use App\Features\Stock\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menjaga perbaikan hasil audit UI (Lighthouse & Axe Core) agar tidak regresi.
 */
class AccessibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_has_h1_meta_description_and_required_ids(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('<h1', false)
            ->assertSee('<meta name="description"', false)
            ->assertSee('id="login-username"', false)
            ->assertSee('id="login-password"', false)
            ->assertSee('id="login-submit"', false);
    }

    public function test_register_page_has_h1_and_meta_description(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('<h1', false)
            ->assertSee('<meta name="description"', false);
    }

    public function test_stock_list_page_controls_have_accessible_names(): void
    {
        $user = User::factory()->member()->create();
        Stock::factory()->create(['user_id' => $user->id, 'name' => 'Barang Aksesibel']);

        $this->actingAs($user)
            ->get('/stocks')
            ->assertOk()
            ->assertSee('<h1', false)
            ->assertSee('aria-label="Cari stok barang"', false)
            ->assertSee('for="sl-statusFilter"', false)
            ->assertSee('id="sl-statusFilter"', false)
            ->assertSee('aria-label="Lihat detail Barang Aksesibel"', false);
    }

    public function test_stock_detail_page_has_accessible_back_link_and_heading_order(): void
    {
        $user = User::factory()->member()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id, 'cover_image_path' => null]);

        $this->actingAs($user)
            ->get(route('stocks.show', $stock->id))
            ->assertOk()
            ->assertSee('<h1', false)
            ->assertSee('aria-label="Kembali ke daftar stok"', false)
            // heading cover memakai h2 (tidak melompat dari h1 ke h3)
            ->assertSee('<h2 class="text-sm font-bold text-slate-800">Tambahkan Cover Gambar untuk Barang Ini</h2>', false);
    }

    public function test_profile_form_inputs_are_labelled(): void
    {
        $user = User::factory()->member()->create();

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee('<h1', false)
            ->assertSee('for="pf-name"', false)
            ->assertSee('id="pf-name"', false)
            ->assertSee('for="pf-username"', false)
            ->assertSee('id="pf-username"', false)
            ->assertSee('for="pf-email"', false)
            ->assertSee('id="pf-email"', false);
    }

    public function test_admin_pages_have_accessible_names(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->member()->create();

        $this->actingAs($admin)
            ->get('/admin/users')
            ->assertOk()
            ->assertSee('aria-label="Cari pengguna"', false);

        $this->actingAs($admin)
            ->get("/admin/users/{$member->id}/stocks")
            ->assertOk()
            ->assertSee('aria-label="Cari stok barang"', false)
            ->assertSee('aria-label="Kembali ke daftar pengguna"', false)
            ->assertSee('for="au-statusFilter"', false);
    }
}
