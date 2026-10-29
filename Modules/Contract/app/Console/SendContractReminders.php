<?php

namespace Modules\Contract\Console;

use Illuminate\Console\Command;
use Modules\Contract\Services\ContractReminders;

/**
 * Scheduled daily by the module (ADR 0021).
 */
class SendContractReminders extends Command
{
    protected $signature = 'contracts:send-reminders';

    protected $description = 'Remind staff of contracts about to end and people of contracts waiting for their signature';

    public function handle(ContractReminders $reminders): int
    {
        $expiring = $reminders->expiring();
        $unsigned = $reminders->unsigned();

        $this->info("Expiring contracts reminded: {$expiring}; unsigned contracts reminded: {$unsigned}.");

        return self::SUCCESS;
    }
}
