<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\{User, Profile};

class ProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for ($i = 0; $i < 200; $i++) {
            $user = User::factory()->create();

            Profile::factory()->for($user)->create([
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url,
            ]);
        }
    }
}
