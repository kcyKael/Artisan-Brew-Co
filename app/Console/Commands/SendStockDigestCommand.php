<?php

namespace App\Console\Commands;

use App\Services\Email\EmailSettingsService;
use App\Services\Email\StockAlertService;
use Illuminate\Console\Command;

class SendStockDigestCommand extends Command
{
    protected $signature = 'abc:emails-stock-digest {--force : Send digest even if stock alert mode is immediate}';

    protected $description = 'Send a consolidated low-stock and sold-out digest email to owners/admins';

    public function handle(StockAlertService $stockAlerts, EmailSettingsService $settings): int
    {
        if (! $this->option('force') && $settings->stockAlertMode() !== 'digest') {
            $this->info('Stock alert mode is not set to digest.');

            return self::SUCCESS;
        }

        $count = $stockAlerts->sendDigest(null);
        $this->info("Queued {$count} stock digest email(s).");

        return self::SUCCESS;
    }
}
