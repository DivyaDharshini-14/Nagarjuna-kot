<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Company;
use App\Models\Floor;
use App\Models\Kitchen;
use App\Models\KitchenMenuItem;
use App\Models\MenuItem;
use App\Models\MenuType;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrderDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException(
                'OrderDemoSeeder creates sample restaurant data and cannot run in production.',
            );
        }

        $company = Company::where('code', 'NAGARJUNA')->first();
        $branch = Branch::where('code', 'IND-01')->first();

        if (! $company || ! $branch) {
            return;
        }

        // 1. Kitchens
        $biryaniKitchen = Kitchen::firstOrCreate(
            ['code' => 'K-BIR', 'branch_id' => $branch->id],
            [
                'company_id' => $company->id,
                'name' => 'Biryani Kitchen',
                'description' => 'Specialty kitchen station',
                'is_active' => true,
            ]
        );

        $chineseKitchen = Kitchen::firstOrCreate(
            ['code' => 'K-CHN', 'branch_id' => $branch->id],
            [
                'company_id' => $company->id,
                'name' => 'Chinese Kitchen',
                'description' => 'Specialty kitchen station',
                'is_active' => true,
            ]
        );

        $startersKitchen = Kitchen::firstOrCreate(
            ['code' => 'K-STR', 'branch_id' => $branch->id],
            [
                'company_id' => $company->id,
                'name' => 'Starters Kitchen',
                'description' => 'Appetizers and starters station',
                'is_active' => true,
            ]
        );

        // 2. Floor & Tables
        $floor = Floor::firstOrCreate(
            ['code' => 'FL-01', 'branch_id' => $branch->id],
            [
                'company_id' => $company->id,
                'name' => 'Ground Floor',
                'is_active' => true,
            ]
        );

        foreach ([
            ['name' => 'Table T-01', 'seating_capacity' => 4],
            ['name' => 'Table T-02', 'seating_capacity' => 2],
            ['name' => 'Table T-03', 'seating_capacity' => 6],
            ['name' => 'Table T-04', 'seating_capacity' => 4],
        ] as $tbl) {
            RestaurantTable::firstOrCreate(
                ['name' => $tbl['name'], 'branch_id' => $branch->id],
                [
                    'company_id' => $company->id,
                    'floor_id' => $floor->id,
                    'seating_capacity' => $tbl['seating_capacity'],
                    'table_status' => 'Available',
                    'is_active' => true,
                ]
            );
        }

        // 3. Menu Types & Categories
        $nonVegType = MenuType::firstOrCreate(
            ['name' => 'Non-Veg', 'company_id' => $company->id],
            ['is_active' => true]
        );
        $vegType = MenuType::firstOrCreate(
            ['name' => 'Veg', 'company_id' => $company->id],
            ['is_active' => true]
        );

        $biryaniCat = Category::firstOrCreate(
            ['code' => 'CAT-BIR', 'company_id' => $company->id],
            ['name' => 'Biryani', 'display_order' => 1, 'is_active' => true]
        );
        $chineseCat = Category::firstOrCreate(
            ['code' => 'CAT-CHN', 'company_id' => $company->id],
            ['name' => 'Chinese', 'display_order' => 2, 'is_active' => true]
        );
        $startersCat = Category::firstOrCreate(
            ['code' => 'CAT-STR', 'company_id' => $company->id],
            ['name' => 'Starters', 'display_order' => 3, 'is_active' => true]
        );

        // 4. Dishes & Kitchen Mappings
        $dishes = [
            [
                'name' => 'Chicken Biryani',
                'code' => 'CB-01',
                'category_id' => $biryaniCat->id,
                'menu_type_id' => $nonVegType->id,
                'selling_price' => 320.00,
                'kitchen_id' => $biryaniKitchen->id,
            ],
            [
                'name' => 'Mutton Biryani',
                'code' => 'MB-01',
                'category_id' => $biryaniCat->id,
                'menu_type_id' => $nonVegType->id,
                'selling_price' => 380.00,
                'kitchen_id' => $biryaniKitchen->id,
            ],
            [
                'name' => 'Veg Fried Rice',
                'code' => 'VFR-01',
                'category_id' => $chineseCat->id,
                'menu_type_id' => $vegType->id,
                'selling_price' => 220.00,
                'kitchen_id' => $chineseKitchen->id,
            ],
            [
                'name' => 'Chicken Noodles',
                'code' => 'CN-01',
                'category_id' => $chineseCat->id,
                'menu_type_id' => $nonVegType->id,
                'selling_price' => 240.00,
                'kitchen_id' => $chineseKitchen->id,
            ],
            [
                'name' => 'Gobi Manchurian',
                'code' => 'GM-01',
                'category_id' => $startersCat->id,
                'menu_type_id' => $vegType->id,
                'selling_price' => 180.00,
                'kitchen_id' => $startersKitchen->id,
            ],
        ];

        foreach ($dishes as $d) {
            $dish = MenuItem::firstOrCreate(
                ['code' => $d['code'], 'company_id' => $company->id],
                [
                    'branch_id' => $branch->id,
                    'category_id' => $d['category_id'],
                    'menu_type_id' => $d['menu_type_id'],
                    'name' => $d['name'],
                    'selling_price' => $d['selling_price'],
                    'display_order' => 1,
                    'stock_tracking_enabled' => false,
                    'availability' => 'available',
                    'is_active' => true,
                ]
            );

            KitchenMenuItem::firstOrCreate(
                ['menu_item_id' => $dish->id, 'kitchen_id' => $d['kitchen_id']],
                [
                    'company_id' => $company->id,
                    'branch_id' => $branch->id,
                    'is_active' => true,
                ]
            );
        }

        User::query()->where('email', 'nagarjuna.kitchen@gmail.com')
            ->first()?->kitchens()->syncWithoutDetaching([$biryaniKitchen->id]);
        User::query()->where('email', 'kitchen@nagarjuna.com')
            ->first()?->kitchens()->syncWithoutDetaching([
                $biryaniKitchen->id,
                $chineseKitchen->id,
                $startersKitchen->id,
            ]);
    }
}

