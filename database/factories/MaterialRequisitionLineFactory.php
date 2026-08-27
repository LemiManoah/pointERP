<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MaterialRequisitionLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaterialRequisitionLine>
 */
final class MaterialRequisitionLineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_name_snapshot' => fake()->words(3, true),
            'unit_code_snapshot' => 'PIECE',
            'unit_symbol_snapshot' => 'pc',
            'requested_quantity' => '10.0000',
            'conversion_multiplier' => '1.0000000000',
            'stock_quantity' => '10.0000',
            'approved_quantity' => '0.0000',
            'issued_quantity' => '0.0000',
            'returned_quantity' => '0.0000',
            'sort_order' => 0,
        ];
    }
}
