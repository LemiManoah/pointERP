<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\EnsureDefaultTenant;
use App\Models\User;
use Illuminate\Database\Seeder;

final class PointInvestmentSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = resolve(EnsureDefaultTenant::class)->handle();

        User::query()->updateOrCreate(
            ['email' => 'lemi@gmail.com'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Lemi',
                'password' => 'password',
                'email_verified_at' => now(),
                'is_active' => true,
                'is_director' => true,
            ],
        );
    }
}
