# ifs24035 - PABWE 2026 Studi Kasus Praktikum 7: Aplikasi Stock Barang (Laravel 13 & Livewire 4)

Aplikasi inventaris **stok barang** dengan arsitektur **Clean Architecture & Feature-First**, dibangun dengan **Laravel 13**, **Livewire 4**, **MySQL**, **PhpOffice/PhpSpreadsheet**, dan integrasi **OpenAI**. Domain `Todo` pada latihan diganti menjadi `Stock`.

## Fitur

| Peran | Kemampuan |
|-------|-----------|
| Guest | Login & register; `/` mengarah ke login |
| Member | Mengelola stok miliknya sendiri saja |
| Admin | Melihat daftar user + jumlah stok, dan stok tiap user **read-only** |

**Member (`/stocks`, `/stocks/{stock}`)**
- Daftar stok: cari (nama / SKU / deskripsi), filter status, pagination.
- CRUD stok (modal): nama, SKU, deskripsi Markdown (Toast UI), quantity, unit, price, min_stock, status.
- **Adjust quantity (+ / −)** dari list maupun detail. Status otomatis menyesuaikan:
  - `quantity <= 0` → `out_of_stock` (merah)
  - `quantity <= min_stock` → `low_stock` (kuning)
  - selain itu → `available` (hijau)
- Detail stok: cover image, deskripsi Markdown, edit & hapus.
- Import / export Excel (PhpSpreadsheet).
- Generate item stok dengan AI → preview → pilih → simpan.
- Profil (ubah data, password, foto).

**Admin**: `/admin/users` dan `/admin/users/{user}/stocks` (read-only, admin tidak dapat mengubah/menghapus stok user lain).

## Keamanan & kualitas
- Middleware `role` + `owns.stock` (`EnsureStockOwnership`), policy `StockPolicy`.
- Rate limit `1000-per-second`, `FORCE_HTTPS`.
- Form login: `id="login-username"`, `id="login-password"`, `id="login-submit"`.
- Coverage `app/` 100% (gate di `Jenkinsfile`), `composer audit` bersih, Trivy scan.

## Struktur Feature-First
```
app/Features/{Admin,Ai,Auth,Profile,Stock}/...
app/Http/Middleware/{EnsureRole,EnsureStockOwnership}.php
```

## Format Excel
Kolom: `A No | B Name | C SKU | D Description | E Quantity | F Unit | G Price | H Min Stock | I Status | J Created At` (kolom J hanya pada export).
Saat import: unit kosong → `pcs`, min stock kosong → `5`, price `-`/kosong → null, status kosong/tidak valid → dihitung otomatis dari quantity.

## Menjalankan
```bash
composer install
cp .env.example .env          # sesuaikan DB_*, OPENAI_*
php artisan key:generate
mysql -u root -e "CREATE DATABASE pabwe_p7_stock"
php artisan migrate --seed
php artisan storage:link
npm install --ignore-scripts && npm run build
php artisan serve
```
Akun seeder: `admin / admin` dan `member / member` (+ 4 contoh stok untuk member).

## Optimasi UI (Lighthouse & Axe Core)
- Tailwind dikompilasi via **Vite** (`@vite('resources/css/app.css')`), bukan CDN, sehingga tidak ada render-blocking script. **Wajib `npm run build`** sebelum deploy (Jenkinsfile sudah melakukannya dan `public/build` ikut ke paket). Jika `public/build` tidak ada, layout otomatis jatuh ke CDN agar situs tetap tampil.
- `<meta name="description">` pada layout (bisa dioverride lewat variabel `$description`).
- Halaman Login/Register memakai `<h1>`; urutan heading tidak melompat.
- Semua input/select punya label terhubung (`for`/`id`) atau `aria-label`; link/tombol ikon punya nama aksesibel.
- Warna teks memenuhi kontras WCAG AA (>= 4.5:1): `slate-600`, `rose-700`, `emerald-700`, dst.
- Regresi dijaga oleh `tests/Feature/AccessibilityTest.php`.

## Pengujian
```bash
composer test        # phpunit --coverage-text (butuh pcov / xdebug)
composer audit
```
