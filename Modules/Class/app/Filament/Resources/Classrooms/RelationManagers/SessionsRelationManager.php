<?php

namespace Modules\Class\Filament\Resources\Classrooms\RelationManagers;

use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Class\Enums\SessionKind;
use Modules\Class\Filament\Actions\SessionActions;
use Modules\Class\Filament\Resources\ClassSessions\Tables\ClassSessionsTable;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\Subject;
use Modules\Class\Services\SessionPlanner;

/**
 * The «جلسه‌ها» tab of a class (ADR 0023): its sessions, with make-up and
 * extra sessions added by hand.
 */
class SessionsRelationManager extends RelationManager
{
    protected static string $relationship = 'sessions';

    protected static ?string $title = 'جلسه‌ها';

    protected static ?string $modelLabel = 'جلسه';

    protected static ?string $pluralModelLabel = 'جلسه‌ها';

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
            ->modifyQueryUsing(fn (Builder $query) => SessionActions::withAttendanceCounts($query->with(['classroom', 'subject', 'teacher', 'substituteTeacher'])))
            ->defaultSort('starts_at')
            ->columns(SessionActions::columns(withClass: false))
            ->filters(ClassSessionsTable::filters(withClass: false))
            ->headerActions([
                Action::make('addSession')
                    ->label('جلسه‌ی جبرانی / فوق‌العاده')
                    ->icon(Heroicon::OutlinedPlus)
                    ->visible(fn (): bool => auth()->user()?->can('update', $this->classroom()) ?? false)
                    ->modalWidth('2xl')
                    ->schema([
                        ToggleButtons::make('kind')
                            ->label('نوع')
                            ->options([SessionKind::MakeUp->value => SessionKind::MakeUp->getLabel(), SessionKind::Extra->value => SessionKind::Extra->getLabel()])
                            ->default(SessionKind::MakeUp->value)
                            ->inline()
                            ->required()
                            ->columnSpanFull(),
                        DatePicker::make('date')->label('تاریخ')->jalali()->minDate(today())->required(),
                        TimePicker::make('start_time')->label('از ساعت')->seconds(false)->required(),
                        TimePicker::make('end_time')->label('تا ساعت')->seconds(false)->after('start_time')->required(),
                        Select::make('subject_id')->label('درس')->options(fn (): array => Subject::options())->searchable(),
                        Select::make('teacher_id')->label('مدرس')->options(fn (SessionPlanner $planner): array => $planner->teacherOptions($this->classroom())),
                        TextInput::make('location')->label('مکان')->helperText('خالی یعنی مکان خود کلاس.')->maxLength(255),
                        Textarea::make('note')->label('توضیح')->rows(2)->columnSpanFull(),
                    ])
                    ->action(fn (array $data, SessionPlanner $planner) => SessionActions::attempt(
                        fn () => $planner->addSession($this->classroom(), $data),
                        'جلسه اضافه شد.',
                    )),
            ])
            ->recordActions([SessionActions::recordActions()]);
    }

    private function classroom(): Classroom
    {
        /** @var Classroom */
        return $this->getOwnerRecord();
    }
}
