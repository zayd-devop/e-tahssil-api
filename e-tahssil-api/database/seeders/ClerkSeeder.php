<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ClerkSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\Clerk::create([
            'user_id' => 2,
            'nom' => 'Clerk',
            'prenom' => 'User',
            'type_responsabilite' => 'Clerk',
            'grade' => 'Grade 1',
        ]);
    }
}
