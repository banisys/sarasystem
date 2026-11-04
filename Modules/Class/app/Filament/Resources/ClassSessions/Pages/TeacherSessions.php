<?php

namespace Modules\Class\Filament\Resources\ClassSessions\Pages;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Modules\Class\Models\ClassSession;
use Modules\Class\Services\SessionCalendar;

/**
 * The sessions a person teaches (ADR 0048), from «جلسه‌های تدریس» on the
 * employees list. A session handed to a substitute is the substitute's.
 */
class TeacherSessions extends PersonSessions
{
    protected function heading(): string
    {
        return 'جلسه‌های تدریس';
    }

    protected function calendarSessions(): Builder
    {
        return ClassSession::query()->taughtBy($this->person);
    }

    protected function calendarData(SessionCalendar $calendar, string $view, CarbonImmutable $date): array
    {
        return $calendar->forTeacher($this->person, $view, $date);
    }
}
