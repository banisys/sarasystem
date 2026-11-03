<?php

namespace Modules\Class\Filament\Resources\Classrooms\RelationManagers;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;
use Modules\Class\Enums\SessionKind;
use Modules\Class\Filament\Actions\SessionActions;
use Modules\Class\Filament\Resources\ClassSessions\Tables\ClassSessionsTable;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\ClassSession;
use Modules\Class\Models\Subject;
use Modules\Class\Services\SessionCalendar;
use Modules\Class\Services\SessionPlanner;

/**
 * The «جلسه‌ها» tab of a class (ADR 0023): its sessions on a day, week,
 * month or year calendar, or as a list (ADR 0047), with make-up and extra
 * sessions added by hand.
 */
class SessionsRelationManager extends RelationManager
{
    protected static string $relationship = 'sessions';

    protected static ?string $title = 'جلسه‌ها';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedClock;

    protected static ?string $modelLabel = 'جلسه';

    protected static ?string $pluralModelLabel = 'جلسه‌ها';

    /**
     * @var array<int, ClassSession|null>
     */
    private array $sessionCache = [];

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('view', $ownerRecord) ?? false;
    }

    /**
     * «روزانه», «هفتگی», «ماهانه», «سالانه» or «فهرست» (the table).
     */
    #[Url(as: 'sessions')]
    public string $sessionsView = 'month';

    /**
     * A day in the calendar's period (Y-m-d); empty is today.
     */
    #[Url(as: 'on')]
    public string $sessionsDate = '';

    /**
     * The calendar of the tab (ADR 0047), or the list.
     */
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('class::filament.session-calendar')
                ->viewData(fn (SessionCalendar $calendar): array => [
                    'calendar' => $this->isShowingList() ? null : $calendar->forClassroom($this->classroom(), $this->sessionsView, $this->calendarDate()),
                    'views' => ['day' => 'روزانه', 'week' => 'هفتگی', 'month' => 'ماهانه', 'year' => 'سالانه', 'list' => 'فهرست'],
                    'current' => $this->isShowingList() ? 'list' : $this->sessionsView,
                    'tones' => SessionCalendar::toneLabels(),
                ]),
            EmbeddedTable::make()->visible(fn (): bool => $this->isShowingList()),
        ]);
    }

    public function showSessions(string $view, ?string $date = null): void
    {
        $this->sessionsView = in_array($view, [...SessionCalendar::VIEWS, 'list'], true) ? $view : 'month';

        if ($date !== null) {
            $this->sessionsDate = $this->parseDate($date)?->toDateString() ?? '';
        }
    }

    /**
     * Arguments: `session` (id), one of this class's sessions.
     */
    public function sessionDetailsAction(): Action
    {
        return SessionActions::details()
            ->record(fn (array $arguments): ?ClassSession => $this->session($arguments['session'] ?? null))
            ->visible(fn (?ClassSession $record): bool => $record !== null);
    }

    public function addSessionAction(): Action
    {
        return Action::make('addSession')
            ->label('جلسه‌ی جبرانی / فوق‌العاده')
            ->icon(Heroicon::OutlinedPlus)
            ->visible(fn (): bool => auth()->user()?->can('update', $this->classroom()) ?? false)
            ->modalWidth('2xl')
            ->fillForm(fn (array $arguments): array => ['kind' => SessionKind::MakeUp->value, 'date' => $this->parseDate($arguments['date'] ?? null)?->max(today())->toDateString()])
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
            ));
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => SessionActions::withAttendanceCounts($query->with(['classroom', 'subject', 'teacher', 'substituteTeacher'])))
            ->defaultSort('starts_at')
            ->columns(SessionActions::columns(withClass: false))
            ->filters(ClassSessionsTable::filters(withClass: false))
            ->headerActions([$this->addSessionAction()])
            ->recordActions([SessionActions::recordActions()]);
    }

    private function isShowingList(): bool
    {
        return $this->sessionsView === 'list';
    }

    private function calendarDate(): CarbonImmutable
    {
        return $this->parseDate($this->sessionsDate) ?? today()->toImmutable();
    }

    private function parseDate(?string $date): ?CarbonImmutable
    {
        if (! is_string($date) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return null;
        }

        return rescue(fn () => CarbonImmutable::createFromFormat('!Y-m-d', $date), report: false) ?: null;
    }

    /**
     * Remembered for the request: the modal's entries and buttons each ask
     * for the record.
     */
    private function session(mixed $id): ?ClassSession
    {
        if (! is_numeric($id)) {
            return null;
        }

        return $this->sessionCache[(int) $id] ??= $this->classroom()->sessions()
            ->with(['classroom', 'subject', 'teacher', 'substituteTeacher'])
            ->find((int) $id);
    }

    private function classroom(): Classroom
    {
        /** @var Classroom */
        return $this->getOwnerRecord();
    }
}
