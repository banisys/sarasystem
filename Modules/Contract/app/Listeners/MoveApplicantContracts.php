<?php

namespace Modules\Contract\Listeners;

use App\Events\ApplicantAccepted;
use Modules\Contract\Services\Contracts;

/**
 * Contracts prepared for an applicant go with them when they are hired or
 * admitted, and are issued for signing (ADR 0020).
 */
class MoveApplicantContracts
{
    public function __construct(private Contracts $contracts) {}

    public function handle(ApplicantAccepted $event): void
    {
        $this->contracts->accepted($event->applicant, $event->member, $event->actor);
    }
}
