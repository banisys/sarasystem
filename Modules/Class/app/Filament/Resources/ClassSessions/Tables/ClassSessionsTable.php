<?php

namespace Modules\Class\Filament\Resources\ClassSessions\Tables;

use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Class\Enums\SessionKind;
use Modules\Class\Enums\SessionStatus;
use Modules\Class\Filament\Actions\SessionActions;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\ClassSession;
use Modules\Class\Models\Subject;

class ClassSessionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => SessionActions::withAttendanceCounts($query->with(['classroom', 'subject', 'teacher', 'substituteTeacher'])))
            ->defaultSort('starts_at')
            ->columns(SessionActions::columns(withClass: true))
            ->filters(self::filters(withClass: true))
            ->recordActions([SessionActions::recordActions()]);
    }

    /**
     * @return list<mixed>
     */
    public static function filters(bool $withClass): array
    {
        return array_values(array_filter([
            Filter::make('dates')
                ->schema([
                    DatePicker::make('from')->label('از تاریخ')->jalali()->default(today()),
                    DatePicker::make('until')->label('تا تاریخ')->jalali(),
                ])
                ->query(fn (Builder $query, array $data) => $query
                    ->when($data['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('starts_at', '>=', $from))
                    ->when($data['until'] ?? null, fn (Builder $query, string $until) => $query->whereDate('starts_at', '<=', $until))),
            $withClass ? SelectFilter::make('classroom_id')
                ->label('کلاس')
                ->options(fn (): array => Classroom::query()->orderBy('title')->pluck('title', 'id')->all())
                ->searchable() : null,
            SelectFilter::make('teacher')
                ->label('مدرس')
                ->options(fn (): array => User::query()
                    ->whereIn('id', ClassSession::query()->select('teacher_id'))
                    ->orWhereIn('id', ClassSession::query()->select('substitute_teacher_id'))
                    ->orderBy('name')->pluck('name', 'id')->all())
                ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn (Builder $query, string $teacher) => $query
                    ->where(fn (Builder $query) => $query
                        ->where('substitute_teacher_id', $teacher)
                        ->orWhere(fn (Builder $query) => $query->where('teacher_id', $teacher)->whereNull('substitute_teacher_id')))))
                ->searchable(),
            SelectFilter::make('subject_id')
                ->label('درس')
                ->options(fn (): array => Subject::definedSubjects()->pluck('title', 'id')->all()),
            SelectFilter::make('status')
                ->label('وضعیت')
                ->options(SessionStatus::class),
            SelectFilter::make('kind')
                ->label('نوع')
                ->options(SessionKind::class),
        ]));
    }
}
