<?php

namespace Modules\Class\Filament\Resources\Classrooms\RelationManagers;

use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Modules\Class\Enums\SessionKind;
use Modules\Class\Filament\Actions\SessionActions;
use Modules\Class\Filament\Resources\ClassSessions\ClassSessionResource;
use Modules\Class\Models\ClassSession;
use Modules\Class\Services\SessionCalendar;

class ExamSchedulesRelationManager extends RelationManager
{
    protected static string $relationship = 'sessions';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Tenant::current()?->getKey() === $ownerRecord->tenant_id && Gate::allows('view', $ownerRecord);
    }

    #[On('class-exam-schedule-added')]
    public function refreshExamList(): void
    {
        $this->resetPage();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('برنامهٔ امتحانی')
            ->queryStringIdentifier('examSchedule')
            ->modifyQueryUsing(function (Builder $query): Builder {
                abort_unless(static::canViewForRecord($this->getOwnerRecord(), $this->getPageClass()), 404);

                return $query->where('kind', SessionKind::Exam)->with(['subject', 'teachers', 'substitutes']);
            })
            ->defaultSort('starts_at', 'desc')
            ->emptyStateHeading('جلسهٔ امتحانی ثبت نشده است')
            ->columns([
                TextColumn::make('starts_at')
                    ->label('زمان')
                    ->formatStateUsing(fn (ClassSession $record): string => SessionActions::when($record))
                    ->sortable(),
                TextColumn::make('subject.title')
                    ->label('درس')
                    ->placeholder('—'),
                TextColumn::make('invigilators')
                    ->label('مراقبان')
                    ->state(fn (ClassSession $record): ?string => $record->teacherNames())
                    ->wrap()
                    ->placeholder('—'),
                TextColumn::make('location')
                    ->label('مکان')
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->formatStateUsing(fn (ClassSession $record): string => SessionCalendar::toneLabels()[SessionCalendar::tone($record)]),
            ])
            ->recordActions([
                Action::make('viewExam')
                    ->label('جزئیات')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn (ClassSession $record): string => ClassSessionResource::detailsUrl($record)),
            ]);
    }
}
