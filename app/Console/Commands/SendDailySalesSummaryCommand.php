<?php

namespace App\Console\Commands;

use App\Services\Email\DailySalesSummaryService;
use App\Services\Email\EmailSettingsService;
use App\Support\BusinessTime;
use Illuminate\Console\Command;

class SendDailySalesSummaryCommand extends Command
{
    protected $signature = 'abc:emails-daily-summary {--date= : Manila closing date YYYY-MM-DD} {--force : Ignore schedule clock and generate distinct resend key}';

    protected $description = 'Prepare and queue the daily sales summary email for configured owners/admins';

    public function handle(DailySalesSummaryService $dailySummary, EmailSettingsService $settings): int
    {
        $dateOption = $this->option('date');
        $force = (bool) $this->option('force');

        if (! $dateOption && ! $force) {
            $nowManila = BusinessTime::nowManila();
            $closingTime = $settings->dailySummaryTime();
            [$hour, $minute] = array_map('intval', explode(':', $closingTime));
            $scheduledAt = $nowManila->setTime($hour, $minute, 0);

            if ($nowManila->lt($scheduledAt)) {
                $this->info("Closing time ({$closingTime} PHT) not yet reached for today.");

                return self::SUCCESS;
            }
        }

        $result = $dailySummary->generateAndQueue(
            is_string($dateOption) && $dateOption !== '' ? $dateOption : null,
            $force,
            null
        );

        $this->info('Queued '.$result['queued'].' daily sales summary email(s).');

        return self::SUCCESS;
    }
}
