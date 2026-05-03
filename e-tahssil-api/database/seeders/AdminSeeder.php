<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\Admin::create([
            'user_id' => 1, 
            'nom' => 'Admin',
            'prenom' => 'User',
            'type_responsabilite' => 'Super Admin',
        ]);
    }
}
