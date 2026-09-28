<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedOptionalUser(
            config('sigati.bootstrap.engineer.email'),
            config('sigati.bootstrap.engineer.password'),
            config('sigati.bootstrap.engineer.name'),
            'engineer'
        );

        $this->seedOptionalUser(
            config('sigati.bootstrap.technician.email'),
            config('sigati.bootstrap.technician.password'),
            config('sigati.bootstrap.technician.name'),
            'technician'
        );
    }

    private function seedOptionalUser(?string $email, ?string $password, string $name, string $role): void
    {
        if (blank($email) && blank($password)) {
            return;
        }

        if (blank($email) || blank($password)) {
            throw new \RuntimeException("Both bootstrap email and password are required for the {$role} user.");
        }

        $user = User::firstOrCreate(
            ['email' => strtolower(trim($email))],
            ['name' => $name, 'password' => Hash::make($password), 'active' => true]
        );

        $user->syncRoles([$role]);
    }
}
