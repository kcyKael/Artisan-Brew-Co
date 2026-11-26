<?php

namespace App\Console\Commands;

use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;

class SeedDemoCommand extends Command
{
    protected $signature = 'abc:seed-demo';

    protected $description = 'Seed non-destructive demo staff, customers, cafe menu, variants, add-ons, and sample orders for local demonstration.';

    public function handle(DemoSeeder $seeder): int
    {
        $this->info('Seeding Artisan Brew Co. demo dataset (non-destructive)...');
        $seeder->setContainer($this->laravel)->setCommand($this)->__invoke();
        $this->info('Demo dataset ready.');

        return self::SUCCESS;
    }
}
