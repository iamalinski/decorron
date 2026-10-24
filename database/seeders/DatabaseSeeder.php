<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Catalog\Database\Seeders\CatalogSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Създава администраторски профил и начален каталог.
     *
     * Паролата НЕ се записва в кода. Подава се през променливи на средата:
     *   ADMIN_EMAIL=... ADMIN_PASSWORD=... php artisan db:seed
     * Ако не е подадена, се генерира случайна и се отпечатва веднъж в конзолата.
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@decorron.local');
        $password = env('ADMIN_PASSWORD');
        $generated = false;

        if (blank($password)) {
            $password = Str::password(16);
            $generated = true;
        }

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => env('ADMIN_NAME', 'Администратор'),
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ],
        );

        CatalogSeeder::seedUnitsFor($user);
        $this->callWith(CatalogSeeder::class, ['user' => $user]);

        $this->command?->info('Потребител: '.$email);

        if ($generated) {
            $this->command?->warn('Генерирана парола (запишете я сега, няма да бъде показана отново): '.$password);
        }
    }
}
