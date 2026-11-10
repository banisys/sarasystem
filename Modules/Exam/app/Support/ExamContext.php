<?php

namespace Modules\Exam\Support;

use Carbon\CarbonInterface;

/** The host module's authorized, tenant-scoped description of an exam context. */
readonly class ExamContext
{
    public function __construct(
        public string $type,
        public int $id,
        public int $tenantId,
        public string $title,
        public CarbonInterface $startsAt,
        public CarbonInterface $endsAt,
        public string $backUrl,
        public bool $canManage,
        public bool $canParticipate,
    ) {}
}
