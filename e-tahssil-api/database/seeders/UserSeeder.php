<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Création du premier utilisateur
        User::create([
            'name' => 'أمينة أبوحماد',
            'email' => 'amina@gmail.com',
            'password' => Hash::make('1234'), // N'oublie pas de hacher le mot de passe !
        ]);

        // Création du deuxième utilisateur
        User::create([
            'name' => 'اكرام بوحموشي',
            'email' => 'ikram@gmail.com',
            'password' => Hash::make('1234'),
        ]);
    }
}
