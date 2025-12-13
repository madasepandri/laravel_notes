<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Organization;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $organizations = Organization::all();

        foreach ($organizations as $organization) {
            Category::create([
                'name' => 'General ' . $organization->name,
                'organization_id' => $organization->id,
            ]);
            Category::create([
                'name' => 'Work ' . $organization->name,
                'organization_id' => $organization->id,
            ]);
            Category::create([
                'name' => 'Personal ' . $organization->name,
                'organization_id' => $organization->id,
            ]);
        }
    }
}
