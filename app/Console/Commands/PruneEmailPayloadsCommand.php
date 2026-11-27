<?php

namespace App\Console\Commands;

use App\Services\Email\TransactionalEmailService;
use Illuminate\Console\Command;

class PruneEmailPayloadsCommand extends Command
{
    protected $signature = 'abc:emails-prune-payloads';

    protected $description = 'Prune encrypted payloads from completed sensitive and aged email deliveries';

    public function handle(TransactionalEmailService $transactional): int
    {
        $pruned = $transactional->prunePayloads();
        $this->info("Pruned {$pruned} encrypted email payload record(s).");

        return self::SUCCESS;
    }
}
