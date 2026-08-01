<?php

declare(strict_types=1);

namespace App\Actions\Foundation\Countries;

use App\Models\Country;

final class ToggleCountryStatus
{
    public function handle(Country $country): Country
    {
        $country->update([
            'is_active' => ! $country->is_active,
        ]);

        return $country;
    }
}
