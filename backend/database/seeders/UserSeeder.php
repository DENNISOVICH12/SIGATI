<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $engineer = User::updateOrCreate(
            [
                'email' => 'ingeniero@sigati.local',
            ],
            [
                'name' => 'Ingeniero SIGATI',
                'password' => Hash::make('Sigati2026*'),
            ]
        );

        $engineer->syncRoles(['engineer']);

        $technician = User::updateOrCreate(
            [
                'email' => 'tecnico@sigati.local',
            ],
            [
                'name' => 'Técnico SIGATI',
                'password' => Hash::make('Sigati2026*'),
            ]
        );

        $technician->syncRoles(['technician']);
    }
}