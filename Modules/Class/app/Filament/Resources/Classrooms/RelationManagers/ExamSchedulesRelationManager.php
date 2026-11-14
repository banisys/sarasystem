<?php

namespace Modules\Class\Filament\Resources\Classrooms\RelationManagers;

use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Modules\Class\Enums\SessionKind;
use Modules\Class\Filament\Actions\SessionActions;
use Modules\Class\Filament\Resources\ClassSessions\ClassSessionResource;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\ClassSession;
use Modules\Class\Models\Subject;
use Modules\Class\Services\SessionCalendar;
use Modules\Class\Services\SessionPlanner;

class ExamSchedulesRelationManager extends RelationManager
{
    protected static string $relationship = 'sessions';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Tenant::current()?->getKey() === $ownerRecord->tenant_id && Gate::allows('view', $ownerRecord);
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
            ->headerActions([$this->addExamScheduleAction()])
            ->recordActions([
                Action::make('viewExam')
                    ->label('جزئیات')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn (ClassSession $record): string => ClassSessionResource::detailsUrl($record)),
            ]);
    }

    private function addExamScheduleAction(): Action
    {
        return Action::make('addExamSchedule')
            ->label('افزودن برنامه امتحانی')
            ->icon(Heroicon::OutlinedClipboardDocumentList)
            ->color('gray')
            ->authorize(fn (): bool => Gate::allows('update', $this->classroom()))
            ->modalWidth('3xl')
            ->modalDescription('برای هر امتحان یک جلسه اضافه کنید. جلسه‌های ثبت‌شده در فهرست برنامهٔ امتحانی و تقویم همین کلاس دیده می‌شوند.')
            ->modalSubmitActionLabel('ثبت جلسه‌های امتحانی')
            ->schema([
                Repeater::make('sessions')
                    ->label('جلسه‌های امتحانی')
                    ->addActionLabel('افزودن جلسهٔ امتحانی')
                    ->minItems(1)
                    ->maxItems(30)
                    ->defaultItems(1)
                    ->reorderable(false)
                    ->columns(2)
                    ->schema([
                        DatePicker::make('date')
                            ->label('تاریخ')
                            ->jalali()
                            ->required(),
                        Select::make('subject_id')
                            ->label('درس')
                            ->options(fn (): array => Subject::options())
                            ->searchable(),
                        TimePicker::make('start_time')
                            ->label('ساعت شروع')
                            ->seconds(false)
                            ->rules(['date_format:H:i,H:i:s'])
                            ->required(),
                        TimePicker::make('end_time')
                            ->label('ساعت پایان')
                            ->seconds(false)
                            ->rules(['date_format:H:i,H:i:s'])
                            ->after('start_time')
                            ->required(),
                        Select::make('teacher_ids')
                            ->label('مراقبان')
                            ->helperText('کارکنان همهٔ '.config('sarasystem.tenant_plural_label').' را می‌توانید با نام جست‌وجو کنید.')
                            ->multiple()
                            ->searchable()
                            ->options(fn (SessionPlanner $planner): array => $this->examInvigilatorOptions($planner))
                            ->getSearchResultsUsing(fn (string $search, SessionPlanner $planner): array => $this->examInvigilatorOptions($planner, $search))
                            ->getOptionLabelsUsing(fn (array $values, SessionPlanner $planner): array => $this->examInvigilatorOptions($planner, selected: $values)),
                        TextInput::make('location')
                            ->label('مکان')
                            ->helperText('خالی یعنی مکان خود کلاس.')
                            ->maxLength(255),
                        Textarea::make('note')
                            ->label('توضیحات')
                            ->rows(2)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                    ]),
            ])
            ->action(function (array $data, SessionPlanner $planner): void {
                Gate::authorize('update', $this->classroom());

                SessionActions::attempt(
                    fn () => $planner->addExamSessions($this->classroom(), $data['sessions']),
                    'جلسه‌های امتحانی ثبت شدند.',
                );

                $this->resetPage();
            });
    }

    /**
     * @param  list<int|string>|null  $selected
     * @return array<int, string>
     */
    private function examInvigilatorOptions(SessionPlanner $planner, string $search = '', ?array $selected = null): array
    {
        Gate::authorize('update', $this->classroom());

        return $planner->invigilatorOptions($search, $selected);
    }

    private function classroom(): Classroom
    {
        $classroom = $this->getOwnerRecord();
        abort_unless(static::canViewForRecord($classroom, $this->getPageClass()), 404);

        return $classroom;
    }
}
