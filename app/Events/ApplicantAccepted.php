<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An applicant (job application, preregistration) became a member
 * (employee, learner), inside the workflow's transaction and the member's
 * tenant. Modules move what belonged to the applicant, e.g. contracts
 * (ADR 0020).
 */
class ApplicantAccepted
{
    use Dispatchable;

    public function __construct(
        public Model $applicant,
        public Model $member,
        public User $actor,
    ) {}
}
