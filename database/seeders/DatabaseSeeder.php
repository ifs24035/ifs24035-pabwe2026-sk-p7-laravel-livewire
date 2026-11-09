<?php

namespace Database\Seeders;

use App\Features\Stock\Models\Stock;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Administrator',
                'username' => 'admin',
                'password' => Hash::make('admin'),
                'role' => 'admin',
            ]
        );

        $member = User::firstOrCreate(
            ['email' => 'member@example.com'],
            [
                'name' => 'Member User',
                'username' => 'member',
                'password' => Hash::make('member'),
                'role' => 'member',
            ]
        );

        Stock::firstOrCreate(
            ['user_id' => $member->id, 'sku' => 'BRG-001'],
            [
                'name' => 'Beras Premium 5 Kg',
                'description' => 'Beras kualitas premium kemasan **5 kg** untuk kebutuhan rumah tangga.',
                'quantity' => 40,
                'unit' => 'karung',
                'price' => 68000,
                'min_stock' => 10,
                'status' => 'available',
            ]
        );

        Stock::firstOrCreate(
            ['user_id' => $member->id, 'sku' => 'BRG-002'],
            [
                'name' => 'Minyak Goreng 2 Liter',
                'description' => 'Minyak goreng kemasan pouch 2 liter. Stok mulai menipis.',
                'quantity' => 4,
                'unit' => 'pouch',
                'price' => 38000,
                'min_stock' => 10,
                'status' => 'low_stock',
            ]
        );

        Stock::firstOrCreate(
            ['user_id' => $member->id, 'sku' => 'BRG-003'],
            [
                'name' => 'Gula Pasir 1 Kg',
                'description' => 'Gula pasir putih kemasan 1 kg. Saat ini **habis**, perlu restock.',
                'quantity' => 0,
                'unit' => 'pack',
                'price' => 17500,
                'min_stock' => 8,
                'status' => 'out_of_stock',
            ]
        );

        Stock::firstOrCreate(
            ['user_id' => $member->id, 'sku' => 'BRG-004'],
            [
                'name' => 'Kopi Bubuk 200 Gram',
                'description' => 'Kopi bubuk robusta kemasan 200 gram.',
                'quantity' => 25,
                'unit' => 'pack',
                'price' => 22000,
                'min_stock' => 5,
                'status' => 'available',
            ]
        );
    }
}
