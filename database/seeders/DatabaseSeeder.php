<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if ($this->command) {
            $this->command->info('Artisan Brew Co. default seeder is intentionally non-destructive.');
            $this->command->line('• Production first admin: php artisan abc:create-admin');
            $this->command->line('• Local demo dataset:   php artisan abc:seed-demo');
        }
    }
}
