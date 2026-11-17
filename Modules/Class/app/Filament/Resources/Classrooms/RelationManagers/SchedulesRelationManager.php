<?php

namespace Modules\Class\Filament\Resources\Classrooms\RelationManagers;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Class\Filament\Actions\TeachingScheduleActions;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\ClassSchedule;
use Modules\Class\Services\PlanForecast;
use Modules\Class\Services\SessionPlanner;

/**
 * The teaching schedule of a class (ADR 0023): when it meets. Saving a schedule
 * builds its sessions (SessionPlanner); whoever may edit the class manages
 * its schedules.
 */
class SchedulesRelationManager extends RelationManager
{
    protected $listeners = ['teaching-schedules-changed' => '$refresh'];

    protected static string $relationship = 'schedules';

    protected static ?string $title = 'برنامه';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedCalendarDays;

    protected static ?string $modelLabel = 'برنامه';

    protected static ?string $pluralModelLabel = 'برنامه‌ها';

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('view', $ownerRecord) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['subject', 'teachers', 'teachingPlan'])->withCount(['sessions' => fn (Builder $sessions) => $sessions->upcoming()->takingPlace()]))
            ->columns([
                TextColumn::make('frequency')
                    ->label('زمان')
                    ->formatStateUsing(fn (ClassSchedule $record): string => $record->summary()),
                TextColumn::make('subject.title')
                    ->label('درس')
                    ->placeholder('—'),
                TextColumn::make('teachers.name')
                    ->label('مدرس‌ها')
                    ->listWithLineBreaks()
                    ->placeholder('—'),
                TextColumn::make('teachingPlan.title')
                    ->label('طرح درس')
                    ->description(function (ClassSchedule $record): ?string {
                        if ($record->teaching_plan_id === null) {
                            return null;
                        }

                        ['covered' => $covered, 'total' => $total] = app(PlanForecast::class)->progress($record);

                        return "پیشرفت: {$covered} از {$total} سرفصل";
                    })
                    ->placeholder('—'),
                TextColumn::make('starts_on')
                    ->label('از')
                    ->jalaliDate(),
                TextColumn::make('ends_on')
                    ->label('تا')
                    ->jalaliDate(),
                TextColumn::make('location')
                    ->label('مکان')
                    ->placeholder('همان مکان کلاس')
                    ->toggleable(),
                TextColumn::make('sessions_count')
                    ->label('جلسه‌های پیش رو'),
            ])
            ->recordActions([
                TeachingScheduleActions::edit($this->classroom())
                    ->after(fn () => $this->dispatch('teaching-schedules-changed')->to(SessionsRelationManager::class)),
                Action::make('deleteSchedule')
                    ->label('حذف')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('جلسه‌های پیش روی این برنامه هم حذف می‌شوند؛ جلسه‌های گذشته و آن‌هایی که دستی تغییر کرده‌اند می‌مانند.')
                    ->authorize(fn (): bool => TeachingScheduleActions::canManage($this->classroom()))
                    ->action(fn (ClassSchedule $record, SessionPlanner $planner) => $planner->deleteSchedule($record))
                    ->after(fn () => $this->dispatch('teaching-schedules-changed')->to(SessionsRelationManager::class)),
            ]);
    }

    private function classroom(): Classroom
    {
        /** @var Classroom */
        return $this->getOwnerRecord();
    }
}
