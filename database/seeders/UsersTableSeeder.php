<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UsersTableSeeder extends Seeder
{
    public function run()
    {
        // Create 20 users with random balances
        User::factory(20)->create();

        // Create a test user with known credentials for easy testing
        User::factory()->create([
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
            'balance' => fake()->randomFloat(2, 100, 10000), // Random balance between 100 and 10,000
        ]);
    }
}
