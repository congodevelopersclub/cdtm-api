<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'Frontend',
            'Backend',
            'Data',
            'DBA',
            'Network',
            'OPS',
            'SysOps',
            'DevOps',
            'Management',
            'Mobile',
            'QA',
            'Security',
        ];

        foreach ($categories as $sortOrder => $name) {
            Category::query()->firstOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'is_active' => true,
                    'sort_order' => $sortOrder + 1,
                ]
            );
        }
    }
}
