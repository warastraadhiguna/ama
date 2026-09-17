<?php

namespace Database\Seeders;

use App\Models\ActivityType;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    /**
     * Starter master data from docs sections 15 (Jenis Kegiatan) and 16
     * (Produk). Not exhaustive/final — admins manage this via the
     * master_data.manage-gated CRUD endpoints from here on.
     */
    public function run(): void
    {
        $activityTypes = [
            ['code' => 'DEMPLOT', 'name' => 'Demonstrasi Plot'],
            ['code' => 'PENYULUHAN', 'name' => 'Penyuluhan'],
            ['code' => 'FFD', 'name' => 'Farm Field Day'],
            ['code' => 'STUDY_BANDING', 'name' => 'Study Banding'],
            ['code' => 'BIG_FARMER_DAY', 'name' => 'Big Farmer Day'],
            ['code' => 'KUNJUNGAN_TOKO', 'name' => 'Kunjungan Toko / Kios'],
            ['code' => 'CEK_STOK_TOKO', 'name' => 'Cek Stok Toko / Kios'],
        ];

        foreach ($activityTypes as $activityType) {
            ActivityType::firstOrCreate(['code' => $activityType['code']], $activityType);
        }

        $categoriesWithProducts = [
            'DECOMPOSER' => ['name' => 'Decomposer', 'products' => [['code' => 'BEKA', 'name' => 'BEKA']]],
            'HUMAT' => ['name' => 'Senyawa Humat', 'products' => [['code' => 'POMMIX', 'name' => 'POMMIX']]],
            'POC' => ['name' => 'Pupuk Organik Cair (POC)', 'products' => [['code' => 'POMI', 'name' => 'POMI']]],
        ];

        foreach ($categoriesWithProducts as $code => $definition) {
            $category = ProductCategory::firstOrCreate(['code' => $code], ['name' => $definition['name']]);

            foreach ($definition['products'] as $product) {
                $category->products()->firstOrCreate(['code' => $product['code']], $product);
            }
        }
    }
}
