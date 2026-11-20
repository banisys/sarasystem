<?php

namespace Modules\Class\Filament\Actions;

use App\Models\Tenant;
use App\Support\EmployeeDirectory;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Modules\Class\Enums\SessionStatus;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\ClassSession;
use Modules\Class\Models\Subject;
use Modules\Class\Services\SessionPlanner;

class ExamScheduleActions
{
    public static function add(Classroom $classroom): Action
    {
        return Action::make('addExamSchedule')
            ->label('افزودن برنامه امتحانی')
            ->icon(Heroicon::OutlinedClipboardDocumentList)
            ->color('gray')
            ->size(Size::Small)
            ->authorize(fn (): bool => self::canEdit($classroom))
            ->modalWidth('3xl')
            ->modalDescription('برای هر امتحان یک جلسه اضافه کنید. جلسه‌های ثبت‌شده در تقویم همین کلاس دیده می‌شوند.')
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
                    ->schema(self::fields($classroom)),
            ])
            ->action(function (array $data, SessionPlanner $planner) use ($classroom): void {
                abort_unless(self::canEdit($classroom), 403);

                SessionActions::attempt(
                    fn () => $planner->addExamSessions($classroom, $data['sessions']),
                    'جلسه‌های امتحانی ثبت شدند.',
                );
            });
    }

    public static function edit(): Action
    {
        return Action::make('editExamSchedule')
            ->label('ویرایش')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->authorize(fn (ClassSession $record): bool => self::canEdit($record->classroom))
            ->visible(fn (ClassSession $record): bool => $record->status !== SessionStatus::Cancelled)
            ->modalHeading('ویرایش برنامهٔ امتحانی')
            ->modalWidth('3xl')
            ->modalDescription(fn (ClassSession $record): string => $record->status === SessionStatus::Scheduled && $record->starts_at->isFuture()
                ? 'تغییر زمان جلسه را در تقویم کلاس می‌بینید. زمان آزمون آنلاینِ متصل، تنظیم جداگانه‌ای دارد.'
                : 'برای جلسه‌ای که شروع شده، می‌توانید عنوان، درس، مراقبان، روش برگزاری، مکان و توضیحات را اصلاح کنید.')
            ->fillForm(fn (ClassSession $record): array => [
                ...$record->only(['title', 'subject_id', 'location', 'note']),
                'mode' => $record->deliveryMode()->value,
                'date' => $record->starts_at->toDateString(),
                'start_time' => $record->starts_at->format('H:i'),
                'end_time' => $record->ends_at->format('H:i'),
                'teacher_ids' => $record->teachers->modelKeys(),
            ])
            ->schema(fn (ClassSession $record): array => [Grid::make(2)->schema(self::fields($record->classroom, $record))])
            ->action(function (ClassSession $record, array $data, SessionPlanner $planner): void {
                abort_unless(self::canEdit($record->classroom), 403);

                SessionActions::attempt(
                    fn () => $planner->updateExamSession($record->classroom, $record, $data),
                    'برنامهٔ امتحانی به‌روز شد.',
                );

                $record->refresh();
            });
    }

    /** @return list<DatePicker|Select|TimePicker|TextInput|Textarea|ToggleButtons> */
    private static function fields(Classroom $classroom, ?ClassSession $session = null): array
    {
        $canChangeTime = $session === null || ($session->status === SessionStatus::Scheduled && $session->starts_at->isFuture());

        return [
            ...($session === null ? [] : [TextInput::make('title')->label('عنوان جلسه')->maxLength(255)->columnSpanFull()]),
            DatePicker::make('date')
                ->label('تاریخ')
                ->disabled(! $canChangeTime)
                ->jalali()
                ->required(),
            Select::make('subject_id')
                ->label('درس')
                ->options(fn (): array => Subject::options($session?->subject_id))
                ->searchable(),
            TimePicker::make('start_time')
                ->label('ساعت شروع')
                ->disabled(! $canChangeTime)
                ->seconds(false)
                ->rules(['date_format:H:i,H:i:s'])
                ->required(),
            TimePicker::make('end_time')
                ->label('ساعت پایان')
                ->disabled(! $canChangeTime)
                ->seconds(false)
                ->rules(['date_format:H:i,H:i:s'])
                ->after('start_time')
                ->required(),
            Select::make('teacher_ids')
                ->label('مراقبان')
                ->hidden($session !== null && ! app()->bound(EmployeeDirectory::class))
                ->helperText('کارکنان همهٔ '.config('sarasystem.tenant_plural_label').' را می‌توانید با نام جست‌وجو کنید.')
                ->multiple()
                ->searchable()
                ->options(fn (SessionPlanner $planner): array => self::invigilatorOptions($classroom, $planner))
                ->getSearchResultsUsing(fn (string $search, SessionPlanner $planner): array => self::invigilatorOptions($classroom, $planner, $search))
                ->getOptionLabelsUsing(fn (array $values, SessionPlanner $planner): array => self::invigilatorOptions($classroom, $planner, selected: $values)
                    + ($session?->teachers->whereIn('id', $values)->pluck('name', 'id')->all() ?? [])),
            SessionActions::modeField($classroom->mode),
            SessionActions::locationField(),
            Textarea::make('note')
                ->label('توضیحات')
                ->rows(2)
                ->maxLength(2000)
                ->columnSpanFull(),
        ];
    }

    /**
     * @param  list<int|string>|null  $selected
     * @return array<int, string>
     */
    private static function invigilatorOptions(Classroom $classroom, SessionPlanner $planner, string $search = '', ?array $selected = null): array
    {
        abort_unless(self::canEdit($classroom), 403);

        return $planner->invigilatorOptions($search, $selected);
    }

    private static function canEdit(Classroom $classroom): bool
    {
        return Tenant::current()?->getKey() === $classroom->tenant_id
            && Gate::allows('view', $classroom)
            && Gate::allows('update', $classroom);
    }
}
