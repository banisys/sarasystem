<?php

namespace Modules\Class\Filament\Resources\ClassSessions\Pages;

use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;
use Modules\Class\Filament\Concerns\ShowsSessionCalendar;
use Modules\Class\Filament\Resources\ClassSessions\ClassSessionResource;
use Modules\Class\Models\ClassSession;
use Modules\Class\Services\SessionCalendar;

/**
 * The sessions one person teaches in the current tenant on a calendar
 * (ADR 0048), opened from the employees list («جلسه‌های تدریس»). The
 * same views, colours and details modal as a class's «جلسه‌ها» tab.
 */
class TeacherSessions extends Page
{
    use ShowsSessionCalendar;

    protected static string $resource = ClassSessionResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    #[Locked]
    public User $teacher;

    public function mount(int|string $user): void
    {
        $this->teacher = User::query()->findOrFail($user);

        // Only people of this tenant, or who taught here before leaving it.
        abort_unless($this->teacher->belongsToTenant(Tenant::current()) || $this->calendarSessions()->exists(), 404);
    }

    public function getTitle(): string
    {
        return 'جلسه‌های تدریس '.$this->teacher->name;
    }

    public function getSubheading(): string
    {
        $count = $this->calendarSessions()->count();

        return $count > 0
            ? 'همه‌ی جلسه‌های تدریس در '.Tenant::current()->name.': '.$count
            : 'در '.Tenant::current()->name.' جلسه‌ای تدریس نکرده است.';
    }

    public function getBreadcrumb(): string
    {
        return $this->teacher->name;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([$this->sessionCalendarComponent()]);
    }

    protected function calendarSessions(): Builder
    {
        return ClassSession::query()->taughtBy($this->teacher);
    }

    protected function calendarData(SessionCalendar $calendar, string $view, CarbonImmutable $date): array
    {
        return $calendar->forTeacher($this->teacher, $view, $date);
    }
}
