<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $password = config('seeding.admin_password');

        if (blank($password)) {
            $this->command->error('SEED_ADMIN_PASSWORD is not set in .env');
            return;
        }

        User::create([
            'name' => 'admin',
            'email' => config('seeding.admin_email'),
            'email_verified_at' => now(),
            'password' => Hash::make($password),
            'avatar_url' => '',
            'remember_token' => Str::random(10),
        ]);
    }
}
