<?php

namespace Modules\Class\Filament\Resources\ClassSessions\Pages;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Modules\Class\Models\ClassSession;
use Modules\Class\Services\SessionCalendar;

/**
 * The sessions of a learner's classes (ADR 0048), from «جلسه‌های کلاس» on
 * the learners list, with their own attendance.
 */
class LearnerSessions extends PersonSessions
{
    protected function heading(): string
    {
        return 'جلسه‌های کلاس';
    }

    protected function calendarSessions(): Builder
    {
        return ClassSession::query()->attendedBy($this->person);
    }

    protected function calendarData(SessionCalendar $calendar, string $view, CarbonImmutable $date): array
    {
        return $calendar->forLearner($this->person, $view, $date);
    }
}
