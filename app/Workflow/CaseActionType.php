<?php

namespace App\Workflow;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * One line in a case's history (ADR 0016).
 */
enum CaseActionType: string implements HasColor, HasLabel
{
    case Started = 'started';

    case Approved = 'approved';

    case Completed = 'completed';

    case Rejected = 'rejected';

    case Returned = 'returned';

    case Edited = 'edited';

    case Commented = 'commented';

    case Claimed = 'claimed';

    case Released = 'released';

    /** The subject was changed outside the workflow, e.g. by the applicant. */
    case SubjectUpdated = 'subject_updated';

    public function getLabel(): string
    {
        return match ($this) {
            self::Started => 'شروع فرایند',
            self::Approved => 'تأیید',
            self::Completed => 'تأیید نهایی',
            self::Rejected => 'رد',
            self::Returned => 'برگشت',
            self::Edited => 'ویرایش اطلاعات',
            self::Commented => 'یادداشت',
            self::Claimed => 'برداشتن کار',
            self::Released => 'رها کردن کار',
            self::SubjectUpdated => 'به‌روزرسانی اطلاعات',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Approved, self::Completed => 'success',
            self::Rejected => 'danger',
            self::Returned => 'warning',
            self::Started, self::SubjectUpdated => 'info',
            default => 'gray',
        };
    }
}
