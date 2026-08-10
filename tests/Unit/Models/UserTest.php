<?php

declare(strict_types=1);

use App\Models\User;

test('to array', function (): void {
    $user = User::factory()->create()->refresh();

    expect(array_keys($user->toArray()))
        ->toBe([
            'id',
            'tenant_id',
            'staff_id',
            'name',
            'email',
            'email_verified_at',
            'two_factor_confirmed_at',
            'is_active',
            'is_director',
            'is_support',
            'last_login_at',
            'created_at',
            'updated_at',
        ]);
});
