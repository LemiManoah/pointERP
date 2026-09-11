<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

final class QuarryWorkItemTemplateSeeder extends Seeder
{
    public function run(): void
    {
        new WorkItemTemplateSeeder(quarryOnly: true)->run();
    }
}
