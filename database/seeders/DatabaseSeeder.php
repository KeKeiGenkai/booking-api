<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Иван Иванов',
            'api_token' => 'test-token-1',
        ]);

        User::create([
            'name' => 'Петр Петров',
            'api_token' => 'test-token-2',
        ]);

        User::create([
            'name' => 'Сидор Сидоров',
            'api_token' => 'test-token-3',
        ]);
    }
}
