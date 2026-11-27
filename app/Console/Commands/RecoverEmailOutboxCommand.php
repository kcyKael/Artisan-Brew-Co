<?php

namespace App\Console\Commands;

use App\Services\Email\TransactionalEmailService;
use Illuminate\Console\Command;

class RecoverEmailOutboxCommand extends Command
{
    protected $signature = 'abc:emails-recover-outbox {--older-than=60 : Minimum age in seconds before re-dispatching pending rows}';

    protected $description = 'Re-dispatch stranded queued email deliveries from the durable outbox';

    public function handle(TransactionalEmailService $transactional): int
    {
        $olderThan = max(0, (int) $this->option('older-than'));
        $recovered = $transactional->recoverPending($olderThan);
        $this->info("Recovered {$recovered} queued email delivery job(s).");

        return self::SUCCESS;
    }
}
