<?php

namespace Modules\Exam\Interfaces;

/**
 * A host that authorizes entry to its exams independently of the learner's
 * selected tenant. Its tenantForVisit must check access to the host record.
 */
interface SharedExamContextSource extends ExamContextSource {}
