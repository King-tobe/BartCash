<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $categories = [
            ['name' => 'Electronics',  'slug' => 'electronics',  'icon' => 'laptop'],
            ['name' => 'Clothing',     'slug' => 'clothing',     'icon' => 'shirt'],
            ['name' => 'Furniture',    'slug' => 'furniture',    'icon' => 'sofa'],
            ['name' => 'Services',     'slug' => 'services',     'icon' => 'wrench'],
            ['name' => 'Books',        'slug' => 'books',        'icon' => 'book'],
            ['name' => 'Sports',       'slug' => 'sports',       'icon' => 'football'],
            ['name' => 'Other',        'slug' => 'other',        'icon' => 'box'],
        ];

        foreach ($categories as $category) {
            DB::table('categories')->insert([
                'id'         => Str::uuid()->toString(),
                'name'       => $category['name'],
                'slug'       => $category['slug'],
                'icon'       => $category['icon'],
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('categories')->whereIn('slug', [
            'electronics', 'clothing', 'furniture',
            'services', 'books', 'sports', 'other',
        ])->delete();
    }
};