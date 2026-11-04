<?php

namespace Modules\Class\Filament\Resources\ClassSessions\Pages;

use App\Models\Tenant;
use App\Models\User;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;
use Modules\Class\Filament\Concerns\ShowsSessionCalendar;
use Modules\Class\Filament\Resources\ClassSessions\ClassSessionResource;
use Modules\Class\Models\ClassSession;

/**
 * One person's sessions in the current tenant on a calendar (ADR 0048): the
 * same views and colours as a class's «جلسه‌ها» tab. Opened
 * from the employees list (TeacherSessions) or the learners list
 * (LearnerSessions).
 */
abstract class PersonSessions extends Page
{
    use ShowsSessionCalendar;

    protected static string $resource = ClassSessionResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    #[Locked]
    public User $person;

    /**
     * The person's sessions in the current tenant.
     *
     * @return Builder<ClassSession>
     */
    abstract protected function calendarSessions(): Builder;

    /**
     * «جلسه‌های تدریس», «جلسه‌های کلاس».
     */
    abstract protected function heading(): string;

    public function mount(int|string $user): void
    {
        $this->person = User::query()->findOrFail($user);

        // Only people of this tenant, or who had sessions here before leaving it.
        abort_unless($this->person->belongsToTenant(Tenant::current()) || $this->calendarSessions()->exists(), 404);
    }

    public function getTitle(): string
    {
        return $this->heading().' '.$this->person->name;
    }

    public function getSubheading(): string
    {
        $count = $this->calendarSessions()->count();

        return $count > 0
            ? 'همه‌ی '.$this->heading().' در '.Tenant::current()->name.': '.$count
            : 'در '.Tenant::current()->name.' جلسه‌ای ندارد.';
    }

    public function getBreadcrumb(): string
    {
        return $this->person->name;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([$this->sessionCalendarComponent()]);
    }
}
