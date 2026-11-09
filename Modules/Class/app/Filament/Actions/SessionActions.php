<?php

namespace Modules\Class\Filament\Actions;

use App\Auth\Impersonation;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Identifiers;
use Ariaieboy\Jalali\CalendarUtils;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Livewire\Component;
use Modules\Class\Enums\AttendanceStatus;
use Modules\Class\Enums\ClassMode;
use Modules\Class\Enums\SessionStatus;
use Modules\Class\Filament\Resources\Classrooms\ClassroomResource;
use Modules\Class\Filament\Resources\ClassSessions\ClassSessionResource;
use Modules\Class\Models\ClassSession;
use Modules\Class\Models\HomeworkSubmission;
use Modules\Class\Models\PlanTopic;
use Modules\Class\Services\AttendanceRegister;
use Modules\Class\Services\ClassException;
use Modules\Class\Services\HomeworkBook;
use Modules\Class\Services\Online\OnlineEntry;
use Modules\Class\Services\Online\OnlineRooms;
use Modules\Class\Services\PlanForecast;
use Modules\Class\Services\SessionCalendar;
use Modules\Class\Services\SessionContentBook;
use Modules\Class\Services\SessionPlanner;
use Modules\Class\Services\SessionScene;

/**
 * Columns and row actions of session lists: a class's «جلسه‌ها» tab and
 * «کلاس‌ها → جلسه‌ها» (ADR 0023). Changing a session needs the right to
 * edit its class; attendance follows AttendanceRegister (ADR 0024) and
 * content SessionContentBook (ADR 0025).
 */
class SessionActions
{
    public const MATERIALS_UPLOAD_DIRECTORY = 'class-materials';

    /**
     * @return list<TextColumn>
     */
    public static function columns(bool $withClass): array
    {
        return array_values(array_filter([
            TextColumn::make('starts_at')
                ->label('زمان')
                ->formatStateUsing(fn (ClassSession $record): string => self::when($record))
                ->description(fn (ClassSession $record): ?string => $record->wasMoved() ? 'جابه‌جا از '.self::when($record, original: true) : null)
                ->sortable(),
            $withClass ? TextColumn::make('classroom.title')->label('کلاس') : null,
            TextColumn::make('subject.title')
                ->label('درس')
                ->placeholder('—'),
            TextColumn::make('teachers')
                ->label('مدرس‌ها')
                ->state(fn (ClassSession $record): ?string => $record->teacherNames())
                ->wrap()
                ->placeholder('—'),
            TextColumn::make('location')
                ->label('مکان')
                ->placeholder('—')
                ->toggleable(),
            TextColumn::make('kind')
                ->label('نوع')
                ->badge()
                ->color('gray')
                ->toggleable(),
            TextColumn::make('status')
                ->label('وضعیت')
                ->badge()
                ->description(fn (ClassSession $record): ?string => $record->cancel_reason),
            TextColumn::make('content')
                ->label('مطالب')
                ->state(fn (ClassSession $record): ?string => self::contentLine($record))
                ->color(fn (ClassSession $record): ?string => $record->hasContent() ? null : 'gray')
                ->wrap()
                ->limit(80),
            TextColumn::make('homework_submissions_count')
                ->label('تکلیف')
                ->state(fn (ClassSession $record): ?string => $record->homework === null ? null
                    : 'تحویل '.($record->homework_submissions_count ?? 0).($record->graded_count ? " · نمره {$record->graded_count}" : ''))
                ->placeholder('—')
                ->toggleable(),
            TextColumn::make('attendance')
                ->label('حضور و غیاب')
                ->state(fn (ClassSession $record): ?string => ($record->attendances_count ?? 0) === 0 ? null
                    : "حاضر {$record->present_count} · غایب {$record->absent_count}")
                ->placeholder('—'),
        ]));
    }

    /**
     * Adds the counts the «تکلیف» and «حضور و غیاب» columns show.
     *
     * @param  Builder<ClassSession>  $query
     * @return Builder<ClassSession>
     */
    public static function withAttendanceCounts(Builder $query): Builder
    {
        return $query->withCount([
            'homeworkSubmissions',
            'homeworkSubmissions as graded_count' => fn (Builder $submissions) => $submissions->whereNotNull('graded_at'),
            'attendances',
            'attendances as present_count' => fn (Builder $attendances) => $attendances->whereIn('status', [AttendanceStatus::Present, AttendanceStatus::Late]),
            'attendances as absent_count' => fn (Builder $attendances) => $attendances->whereIn('status', [AttendanceStatus::Absent, AttendanceStatus::Excused]),
        ]);
    }

    public static function recordActions(): ActionGroup
    {
        return ActionGroup::make([
            self::details(),
            self::attendance(),
            self::content(),
            self::grading(),
            self::move(),
            self::substitute(),
            self::cancel(),
            self::restore(),
            self::delete(),
        ]);
    }

    /**
     * Opens the session's page in a new tab (ADR 0050).
     */
    public static function details(): Action
    {
        return Action::make('sessionDetails')
            ->label('جزئیات')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->url(fn (ClassSession $record): string => ClassSessionResource::getUrl('view', ['record' => $record]))
            ->openUrlInNewTab();
    }

    /**
     * The session's page, in the look of a class's «اطلاعات» (the same fact
     * cards): the facts on top, then what it covered beside its attendance.
     *
     * @return list<Section|Grid>
     */
    public static function detailsEntries(): array
    {
        return [
            Section::make('مشخصات جلسه')
                ->description(fn (ClassSession $record): ?string => self::lastChange('آخرین تغییر زمان، وضعیت یا مدرس‌ها', $record->changedBy, $record->changed_at))
                ->extraAttributes(['class' => 'classroom-details-section'])
                ->columns(['default' => 1, 'sm' => 2, 'lg' => 3])
                ->schema([
                    TextEntry::make('status')
                        ->label('وضعیت')
                        ->badge()
                        ->helperText(fn (ClassSession $record): ?string => match (SessionCalendar::tone($record)) {
                            'pending' => 'زمان جلسه گذشته و حضور و غیاب یا مطالب آن ثبت نشده است.',
                            'live' => 'هم‌اکنون در حال برگزاری است.',
                            default => $record->cancel_reason !== null ? 'دلیل لغو: '.$record->cancel_reason : null,
                        }),
                    TextEntry::make('starts_at')
                        ->label('زمان')
                        ->state(fn (ClassSession $record): string => self::when($record))
                        ->helperText(fn (ClassSession $record): ?string => $record->wasMoved() ? 'جابه‌جا از '.self::when($record, original: true) : null),
                    TextEntry::make('teacher')
                        ->label('مدرس‌ها')
                        ->state(fn (ClassSession $record): ?string => $record->teacherNames())
                        ->placeholder('—'),
                    TextEntry::make('location')
                        ->label('مکان')
                        ->state(fn (ClassSession $record): ?string => $record->location ?? $record->classroom?->location)
                        ->placeholder('—'),
                    TextEntry::make('mode')
                        ->label('نحوه‌ی برگزاری')
                        ->state(fn (ClassSession $record): ?ClassMode => $record->classroom?->mode)
                        ->badge()
                        ->icon(fn (?ClassMode $state): ?string => match ($state) {
                            ClassMode::InPerson => 'heroicon-m-building-office-2',
                            ClassMode::Online => 'heroicon-m-video-camera',
                            ClassMode::Blended => 'heroicon-m-square-2-stack',
                            null => null,
                        }),
                    TextEntry::make('kind')
                        ->label('نوع')
                        ->badge()
                        ->color('gray'),
                    // The platform the session meets on, and the way in for
                    // whoever may enter (ADR 0073).
                    TextEntry::make('online_room')
                        ->label('کلاس آنلاین')
                        ->state(fn (ClassSession $record): ?string => app(OnlineRooms::class)->forSession($record)?->platformName())
                        ->suffix(fn (ClassSession $record): ?string => self::canEnterOnline($record) ? ' — ورود به کلاس آنلاین' : null)
                        ->url(fn (ClassSession $record): ?string => self::canEnterOnline($record) ? route('class.online.enter', $record) : null, shouldOpenInNewTab: true)
                        ->icon('heroicon-m-video-camera')
                        ->color(fn (ClassSession $record): string => self::canEnterOnline($record) ? 'primary' : 'gray')
                        ->visible(fn (ClassSession $record): bool => app(OnlineRooms::class)->forSession($record) !== null)
                        ->columnSpanFull(),
                ]),
            Grid::make(['default' => 1, 'lg' => 3])->schema([
                Section::make('مطالب و تکلیف')
                    ->description(function (ClassSession $record): ?string {
                        $graded = $record->homeworkSubmissions()->whereNotNull('graded_at')->with('gradedBy')->latest('graded_at')->first();

                        return implode(' — ', array_filter([
                            self::lastChange('آخرین ثبت مطالب', $record->contentRecordedBy, $record->content_recorded_at),
                            self::lastChange('آخرین نمره‌دهی', $graded?->gradedBy, $graded?->graded_at),
                        ])) ?: null;
                    })
                    ->extraAttributes(['class' => 'classroom-details-section'])
                    ->columnSpan(['lg' => 2])
                    ->schema([
                        TextEntry::make('content')
                            ->label(fn (ClassSession $record): string => $record->hasContent() || self::coveredOrPlanned($record) === null ? 'مطالب' : 'مطالب (برنامه)')
                            ->state(fn (ClassSession $record): ?string => self::coveredOrPlanned($record) ?: null)
                            ->color(fn (ClassSession $record): ?string => $record->hasContent() ? null : 'gray')
                            ->formatStateUsing(fn (string $state): HtmlString => new HtmlString(nl2br(e($state))))
                            ->placeholder('ثبت نشده'),
                        TextEntry::make('homework')
                            ->label('تکلیف')
                            ->helperText(fn (ClassSession $record): ?string => implode(' · ', array_filter([
                                $record->homework_due_on ? 'مهلت: '.CalendarUtils::strftime('l Y/m/d', $record->homework_due_on->toMutable()) : null,
                                'تحویل‌شده '.$record->homeworkSubmissions()->count().' · نمره‌داده‌شده '.$record->homeworkSubmissions()->whereNotNull('graded_at')->count(),
                            ])))
                            ->formatStateUsing(fn (string $state): HtmlString => new HtmlString(nl2br(e($state))))
                            ->visible(fn (ClassSession $record): bool => $record->homework !== null),
                        TextEntry::make('materials')
                            ->label('فایل‌ها')
                            ->state(fn (ClassSession $record): HtmlString => new HtmlString(collect($record->materialLinks())
                                ->map(fn (array $file): string => '<a href="'.e($file['url']).'" target="_blank" rel="noopener" class="text-primary-600 underline">'.e($file['name']).'</a>')
                                ->join(' · ')))
                            ->visible(fn (ClassSession $record): bool => $record->hasMedia(ClassSession::MATERIALS_COLLECTION)),
                        TextEntry::make('note')
                            ->label('توضیح')
                            ->formatStateUsing(fn (string $state): HtmlString => new HtmlString(nl2br(e($state))))
                            ->visible(fn (ClassSession $record): bool => filled($record->note)),
                    ]),
                Section::make('حضور و غیاب')
                    ->description(function (ClassSession $record): ?string {
                        $last = $record->attendances()->with('recordedBy')->latest('recorded_at')->first();

                        return self::lastChange('آخرین تغییر', $last?->recordedBy, $last?->recorded_at);
                    })
                    ->schema([
                        View::make('class::filament.session-attendance')
                            ->viewData(fn (ClassSession $record): array => ['rows' => self::attendanceRows($record)]),
                        View::make('class::filament.session-scene')
                            ->viewData(fn (ClassSession $record, SessionScene $scene): array => ['scene' => $scene->for($record), 'title' => ($record->subject?->title ?? 'جلسه').' · '.self::when($record)])
                            ->visible(fn (ClassSession $record, SessionScene $scene): bool => ($user = auth()->user()) instanceof User && $scene->canSee($user, $record)),
                    ]),
            ]),
            Section::make('افراد جلسه')
                ->visible(fn (): bool => auth()->user()?->isSuperAdmin() && ! Impersonation::isActive())
                ->columns(['default' => 1, 'sm' => 2, 'lg' => 3])
                ->schema(fn (ClassSession $record): array => self::peopleEntries($record)),
        ];
    }

    /**
     * Session participants and recorded actors, with the existing impersonation
     * action. Building entries from the scoped session also restricts targets.
     *
     * @return list<TextEntry>
     */
    private static function peopleEntries(ClassSession $session): array
    {
        $day = $session->starts_at->toDateString();
        $members = $session->classroom?->members()
            ->where(fn (Builder $query) => $query->whereNull('joined_on')->orWhereDate('joined_on', '<=', $day))
            ->where(fn (Builder $query) => $query->whereNull('left_on')->orWhereDate('left_on', '>=', $day))
            ->with('user')->get()->pluck('user') ?? collect();
        $users = $members->concat($session->teachers)->concat($session->substitutes)
            ->concat(app(AttendanceRegister::class)->attendees($session))
            ->concat([$session->changedBy, $session->contentRecordedBy])
            ->concat($session->attendances()->with('recordedBy')->get()->pluck('recordedBy'))
            ->concat($session->homeworkSubmissions()->with(['user', 'gradedBy'])->get()->flatMap(fn (HomeworkSubmission $submission): array => [$submission->user, $submission->gradedBy]))
            ->filter()->unique('id')->sortBy('name');

        return $users->map(fn (User $user): TextEntry => TextEntry::make('session_user_'.$user->getKey())
            ->hiddenLabel()
            ->state($user->name)
            ->extraAttributes(['class' => '[&_.fi-in-text-affixed-content]:flex-initial'])
            ->suffixAction(UsersTable::impersonateAction()
                ->record($user)
                ->iconButton()
                ->tooltip('ورود با این کاربر')
                ->modalHeading('ورود با حساب '.$user->name)
                ->action(function (Component $livewire) use ($session, $user): void {
                    Impersonation::start(auth()->user(), $user, ClassSessionResource::getUrl('view', ['record' => $session]));

                    $panel = Filament::getPanel('admin');
                    $livewire->redirect($user->canAccessPanel($panel) ? url($panel->getPath()) : route('dashboard'));
                })))
            ->values()->all();
    }

    /**
     * «آخرین تغییر: نام · 1405/08/17 ساعت 10:30», or null when nobody has
     * changed it yet (ADR 0072). Only the last change is kept.
     */
    public static function lastChange(string $label, ?User $by, ?CarbonInterface $at): ?string
    {
        if ($at === null) {
            return null;
        }

        return $label.': '.($by?->name ?? 'نامشخص').' · '.CalendarUtils::strftime('Y/m/d', $at->toMutable()).' ساعت '.$at->format('H:i');
    }

    /**
     * The session's page's buttons: attendance as the main one, content and
     * homework beside it, the rest under «سایر». Deleting the session leaves
     * for its class.
     *
     * @return list<Action|ActionGroup>
     */
    public static function pageActions(): array
    {
        return [
            self::attendance()->color('primary'),
            self::content()->color('gray'),
            self::grading()->color('gray'),
            ActionGroup::make([
                self::move(),
                self::substitute(),
                self::cancel(),
                self::restore(),
                self::delete()->after(fn (ClassSession $record, Page $livewire) => $livewire->redirect(ClassroomResource::getUrl('sessions', ['record' => $record->classroom_id]))),
            ])
                ->label('سایر')
                ->icon(Heroicon::OutlinedEllipsisHorizontal)
                ->color('gray')
                ->button(),
        ];
    }

    /**
     * Runs a SessionPlanner operation and reports its outcome. When it is
     * refused (e.g. a clash) the modal stays open, so the form can be fixed.
     */
    public static function attempt(callable $operation, string $success): void
    {
        try {
            $operation();
        } catch (ClassException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            // Keeps the modal open with what was entered, so it can be corrected.
            throw new Halt;
        }

        Notification::make()->title($success)->success()->send();
    }

    /**
     * E.g. «سه‌شنبه ۱۴۰۵/۰۸/۱۲ · 08:00–09:30». The hours are isolated as
     * left-to-right, or a right-to-left page shows them as 09:30–08:00.
     */
    /**
     * The signed-in person may enter the session's online room (ADR 0073).
     */
    public static function canEnterOnline(ClassSession $session): bool
    {
        $user = auth()->user();

        return $user instanceof User && app(OnlineEntry::class)->offers($user, $session);
    }

    public static function when(ClassSession $session, bool $original = false): string
    {
        $start = $original ? $session->original_starts_at : $session->starts_at;
        $date = CalendarUtils::strftime('l Y/m/d', $start->toMutable());

        return $original ? $date.' '.$start->format('H:i') : $date." · \u{2066}".$start->format('H:i').'–'.$session->ends_at->format('H:i')."\u{2069}";
    }

    /**
     * The attendees with a status each, filled with what was recorded
     * (or «حاضر»).
     */
    private static function attendance(): Action
    {
        return Action::make('attendance')
            ->label('حضور و غیاب')
            ->icon(Heroicon::OutlinedClipboardDocumentCheck)
            ->color('success')
            ->visible(fn (ClassSession $record): bool => ($user = auth()->user()) instanceof User && app(AttendanceRegister::class)->canTake($user, $record))
            ->modalHeading(fn (ClassSession $record): string => 'حضور و غیاب · '.self::when($record))
            ->modalWidth('4xl')
            ->fillForm(function (ClassSession $record, AttendanceRegister $register): array {
                $recorded = $record->attendances()->get()->keyBy('user_id');

                return ['rows' => $register->attendees($record)->map(fn (User $user): array => [
                    'user_id' => $user->getKey(),
                    'name' => $user->name,
                    'status' => ($recorded[$user->getKey()]->status ?? AttendanceStatus::Present)->value,
                    'late_minutes' => $recorded[$user->getKey()]->late_minutes ?? null,
                    'note' => $recorded[$user->getKey()]->note ?? null,
                ])->values()->all()];
            })
            ->schema([
                Repeater::make('rows')
                    ->hiddenLabel()
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->columns(12)
                    ->schema([
                        Hidden::make('user_id'),
                        TextInput::make('name')->hiddenLabel()->disabled()->dehydrated(false)->columnSpan(3),
                        ToggleButtons::make('status')->hiddenLabel()->options(AttendanceStatus::class)->inline()->required()->live()->columnSpan(5),
                        TextInput::make('late_minutes')->hiddenLabel()->placeholder('دقیقه')->numeric()->minValue(1)->maxValue(600)
                            ->visible(fn (Get $get): bool => $get('status') === AttendanceStatus::Late->value || $get('status') === AttendanceStatus::Late)
                            ->columnSpan(1),
                        TextInput::make('note')->hiddenLabel()->placeholder('توضیح')->maxLength(255)->columnSpan(3),
                    ]),
            ])
            ->action(function (array $data, ClassSession $record, AttendanceRegister $register): void {
                /** @var User $user */
                $user = auth()->user();
                $rows = collect($data['rows'] ?? [])->keyBy(fn (array $row): int => (int) $row['user_id'])->all();

                self::attempt(fn () => $register->record($record, $rows, $user), 'حضور و غیاب ثبت شد.');
            });
    }

    /**
     * Any session that hasn't been held (ADR 0066).
     */
    private static function delete(): Action
    {
        return Action::make('deleteSession')
            ->label('حذف')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('این جلسه برای همیشه حذف می‌شود و با تغییر برنامه‌ی کلاس هم برنمی‌گردد.')
            ->visible(fn (ClassSession $record): bool => self::canChange($record) && $record->canBeDeleted())
            ->action(fn (ClassSession $record, SessionPlanner $planner) => self::attempt(
                fn () => $planner->deleteSession($record),
                'جلسه حذف شد.',
            ));
    }

    /**
     * The recorded statuses with how many have each, for the session's page.
     *
     * @return list<array{label: string, count: int, color: string}>
     */
    private static function attendanceRows(ClassSession $session): array
    {
        $counts = $session->attendances()->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return collect(AttendanceStatus::cases())
            ->filter(fn (AttendanceStatus $status): bool => isset($counts[$status->value]))
            ->map(fn (AttendanceStatus $status): array => ['label' => $status->getLabel(), 'count' => (int) $counts[$status->value], 'color' => $status->getColor()])
            ->values()
            ->all();
    }

    /**
     * What the session covered, or what it is expected to (in grey).
     */
    private static function contentLine(ClassSession $session): ?string
    {
        $text = self::coveredOrPlanned($session);

        if ($session->hasContent()) {
            return $text.($session->homework !== null ? ' · تکلیف دارد' : '');
        }

        return $text === null ? null : 'برنامه: '.$text;
    }

    /**
     * The topics and summary recorded, otherwise the topics the plan expects.
     */
    private static function coveredOrPlanned(ClassSession $session): ?string
    {
        if ($session->hasContent()) {
            $topics = PlanTopic::query()->whereKey($session->covered_topic_ids ?? [])->orderBy('sort')->pluck('title')->all();

            return implode('، ', array_filter([...$topics, $session->summary]));
        }

        $planned = app(PlanForecast::class)->topicsFor($session);

        return $planned === [] ? null : implode('، ', array_unique(array_map(fn (PlanTopic $topic): string => $topic->title, $planned)));
    }

    /**
     * Topics finished, summary, homework and materials; filled with what
     * was recorded, or with the topics the plan expects.
     */
    private static function content(): Action
    {
        return Action::make('content')
            ->label('مطالب جلسه')
            ->icon(Heroicon::OutlinedBookOpen)
            ->color('info')
            ->visible(fn (ClassSession $record): bool => ($user = auth()->user()) instanceof User && app(SessionContentBook::class)->canRecord($user, $record))
            ->modalHeading(fn (ClassSession $record): string => 'مطالب جلسه · '.self::when($record))
            ->modalWidth('3xl')
            ->fillForm(fn (ClassSession $record, PlanForecast $forecast): array => [
                'covered_topic_ids' => $record->hasContent()
                    ? ($record->covered_topic_ids ?? [])
                    : array_values(array_unique(array_map(fn (PlanTopic $topic): int => $topic->getKey(), $forecast->topicsFor($record)))),
                'summary' => $record->summary,
                'homework' => $record->homework,
                'homework_due_on' => $record->homework_due_on?->toDateString(),
                'homework_max_score' => $record->homework_max_score ?? config('class.homework.default_max_score'),
            ])
            ->schema(fn (ClassSession $record, PlanForecast $forecast): array => [
                CheckboxList::make('covered_topic_ids')
                    ->label('سرفصل‌هایی که تمام شد')
                    ->helperText('سرفصل تمام‌نشده را علامت نزنید تا به جلسه‌ی بعد برود.')
                    ->options($forecast->topicChoices($record)->mapWithKeys(fn (PlanTopic $topic): array => [
                        $topic->getKey() => ($record->schedule_id === null ? $topic->teachingPlan->title.': ' : '').$topic->title,
                    ])->all())
                    ->disableOptionWhen(fn (string $value): bool => in_array((int) $value, $forecast->coveredTopicIds($record->classroom_id), true)
                        && ! in_array((int) $value, $record->covered_topic_ids ?? [], true))
                    ->columns(2)
                    ->visible($forecast->topicChoices($record)->isNotEmpty()),
                Textarea::make('summary')
                    ->label('شرح مطالب')
                    ->rows(3),
                Textarea::make('homework')
                    ->label('تکلیف')
                    ->rows(2)
                    ->live(onBlur: true),
                DatePicker::make('homework_due_on')
                    ->label('مهلت تکلیف')
                    ->jalali()
                    ->visible(fn (Get $get): bool => filled($get('homework'))),
                TextInput::make('homework_max_score')
                    ->label('نمره‌ی تکلیف از')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(100)
                    ->visible(fn (Get $get): bool => filled($get('homework'))),
                CheckboxList::make('remove_media_ids')
                    ->label('حذف فایل‌ها')
                    ->options(collect($record->materialLinks())->pluck('name', 'id')->all())
                    ->visible($record->hasMedia(ClassSession::MATERIALS_COLLECTION)),
                FileUpload::make('files')
                    ->label('فایل‌های جلسه')
                    ->helperText('جزوه، تمرین یا فایل تکلیف؛ هر فایل تا ۲۰ مگابایت.')
                    ->disk('local')
                    ->directory(self::MATERIALS_UPLOAD_DIRECTORY)
                    ->storeFileNamesIn('file_names')
                    ->visibility('private')
                    ->multiple()
                    ->maxFiles(10)
                    ->maxSize(20480),
            ])
            ->action(function (array $data, ClassSession $record, SessionContentBook $book): void {
                /** @var User $user */
                $user = auth()->user();

                self::attempt(fn () => $book->record($record, $data, $user), 'مطالب جلسه ثبت شد.');
            });
    }

    /**
     * The homework handed in, each with its answer and files, to score and
     * comment on (ADR 0030).
     */
    private static function grading(): Action
    {
        return Action::make('grading')
            ->label('تکالیف دریافتی')
            ->icon(Heroicon::OutlinedInboxArrowDown)
            ->visible(fn (ClassSession $record): bool => ($user = auth()->user()) instanceof User && app(HomeworkBook::class)->canGrade($user, $record))
            ->modalHeading(fn (ClassSession $record): string => 'تکالیف · '.self::when($record))
            ->modalDescription(fn (ClassSession $record): string => 'نمره از '.app(HomeworkBook::class)->maxScore($record).'. نمره و بازخورد خالی یعنی بدون نمره.')
            ->modalWidth('4xl')
            ->fillForm(fn (ClassSession $record): array => ['grades' => $record->homeworkSubmissions()->with(['user', 'session', 'media'])->get()
                ->sortBy('user.name')
                ->map(fn (HomeworkSubmission $submission): array => [
                    'submission_id' => $submission->getKey(),
                    'name' => $submission->user->name,
                    'handed_in' => self::handedIn($submission),
                    'score' => $submission->score,
                    'feedback' => $submission->feedback,
                ])->values()->all()])
            ->schema([
                Repeater::make('grades')
                    ->hiddenLabel()
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->columns(6)
                    ->schema([
                        Hidden::make('submission_id'),
                        Hidden::make('handed_in'),
                        TextInput::make('name')->hiddenLabel()->disabled()->dehydrated(false)->columnSpan(2),
                        TextInput::make('score')->label('نمره')->numeric()->minValue(0)->columnSpan(1),
                        TextInput::make('feedback')->label('بازخورد')->maxLength(1000)->columnSpan(3),
                        Text::make(fn (Get $get): HtmlString => new HtmlString((string) $get('handed_in')))->columnSpanFull(),
                    ]),
            ])
            ->action(function (array $data, ClassSession $record, HomeworkBook $book): void {
                /** @var User $user */
                $user = auth()->user();
                $grades = collect($data['grades'] ?? [])->keyBy(fn (array $row): int => (int) $row['submission_id'])
                    ->map(fn (array $row): array => ['score' => $row['score'] ?? null, 'feedback' => $row['feedback'] ?? null])
                    ->all();

                self::attempt(fn () => $book->grade($record, $grades, $user), 'نمره‌ها ذخیره شد.');
            });
    }

    /**
     * When and what a learner handed in, as HTML: time (late or not), the
     * answer and links to the files.
     */
    private static function handedIn(HomeworkSubmission $submission): string
    {
        $when = e(CalendarUtils::strftime('Y/m/d H:i', $submission->submitted_at->toMutable())).($submission->isLate() ? ' <strong class="text-danger-600">(با تأخیر)</strong>' : '');
        $files = collect($submission->fileLinks())
            ->map(fn (array $file): string => '<a href="'.e($file['url']).'" target="_blank" rel="noopener" class="text-primary-600 underline">'.e($file['name']).'</a>')
            ->join(' · ');

        return '<div class="text-sm"><div class="text-gray-500">تحویل: '.$when.'</div>'
            .($submission->answer !== null ? '<div class="mt-1 whitespace-pre-line">'.e($submission->answer).'</div>' : '')
            .($files !== '' ? '<div class="mt-1">'.$files.'</div>' : '').'</div>';
    }

    private static function move(): Action
    {
        return Action::make('move')
            ->label('جابه‌جایی')
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->visible(fn (ClassSession $record): bool => self::canChange($record) && $record->status === SessionStatus::Scheduled && ! $record->hasStarted())
            ->fillForm(fn (ClassSession $record): array => [
                'date' => $record->starts_at->toDateString(),
                'start_time' => $record->starts_at->format('H:i'),
                'end_time' => $record->ends_at->format('H:i'),
                'location' => $record->location,
            ])
            ->schema([
                DatePicker::make('date')->label('تاریخ')->jalali()->required(),
                TimePicker::make('start_time')->label('از ساعت')->seconds(false)->required(),
                TimePicker::make('end_time')->label('تا ساعت')->seconds(false)->required(),
                TextInput::make('location')->label('مکان')->maxLength(255),
            ])
            ->modalDescription('جلسه‌ی جابه‌جاشده دیگر با تغییر برنامه‌ی کلاس عوض نمی‌شود.')
            ->action(fn (array $data, ClassSession $record, SessionPlanner $planner) => self::attempt(
                fn () => $planner->move($record, $data['date'], $data['start_time'], $data['end_time'], $data['location'] ?? null),
                'جلسه جابه‌جا شد.',
            ));
    }

    /**
     * The session's own teachers, each with an optional substitute (ADR 0068).
     */
    private static function substitute(): Action
    {
        return Action::make('substitute')
            ->label('مدرس‌ها و جایگزین')
            ->icon(Heroicon::OutlinedUserCircle)
            ->visible(fn (ClassSession $record): bool => self::canChange($record) && $record->status !== SessionStatus::Cancelled)
            ->modalDescription('اگر مدرسی نمی‌آید، جایگزینش را کنار نامش انتخاب کنید؛ بقیه‌ی مدرس‌ها سر کلاس می‌مانند. تغییر مدرس‌ها فقط همین جلسه را عوض می‌کند، نه برنامه را.')
            ->modalWidth('2xl')
            ->fillForm(fn (ClassSession $record): array => [
                'rows' => $record->teachers()->get()->map(fn (User $teacher): array => [
                    'teacher_id' => $teacher->getKey(),
                    'substitute_id' => $teacher->pivot->substitute_id,
                ])->all(),
            ])
            ->schema([
                Repeater::make('rows')
                    ->hiddenLabel()
                    ->defaultItems(0)
                    ->reorderable(false)
                    ->addActionLabel('افزودن مدرس')
                    ->columns(2)
                    ->schema([
                        Select::make('teacher_id')
                            ->label('مدرس')
                            ->options(fn (ClassSession $record, SessionPlanner $planner): array => $planner->teacherOptions($record->classroom)
                                + $record->teachers()->get()->mapWithKeys(fn (User $teacher): array => [$teacher->getKey() => $teacher->name])->all())
                            ->distinct()
                            ->required(),
                        Select::make('substitute_id')
                            ->label('جایگزین')
                            ->placeholder('خودش درس می‌دهد')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search, ClassSession $record): array => self::searchTenantUsers($record, $search))
                            ->getOptionLabelUsing(fn (mixed $value): ?string => User::query()->find($value)?->name),
                    ]),
            ])
            ->action(fn (array $data, ClassSession $record, SessionPlanner $planner) => self::attempt(
                fn () => $planner->setTeachers($record, array_values($data['rows'] ?? [])),
                'ذخیره شد.',
            ));
    }

    private static function cancel(): Action
    {
        return Action::make('cancelSession')
            ->label('لغو جلسه')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (ClassSession $record): bool => self::canChange($record) && $record->status === SessionStatus::Scheduled)
            ->schema([
                Textarea::make('reason')->label('دلیل')->required()->maxLength(255)->rows(2),
            ])
            ->action(fn (array $data, ClassSession $record, SessionPlanner $planner) => self::attempt(
                fn () => $planner->cancel($record, $data['reason']),
                'جلسه لغو شد.',
            ));
    }

    private static function restore(): Action
    {
        return Action::make('restore')
            ->label('برگرداندن جلسه')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->requiresConfirmation()
            ->visible(fn (ClassSession $record): bool => self::canChange($record) && $record->status === SessionStatus::Cancelled)
            ->action(fn (ClassSession $record, SessionPlanner $planner) => self::attempt(
                fn () => $planner->restore($record),
                'جلسه برگشت.',
            ));
    }

    private static function canChange(ClassSession $session): bool
    {
        return $session->classroom !== null && (auth()->user()?->can('update', $session->classroom) ?? false);
    }

    /**
     * @return array<int, string>
     */
    private static function searchTenantUsers(ClassSession $session, string $search): array
    {
        $search = Identifiers::toLatinDigits(trim($search));

        if ($search === '') {
            return [];
        }

        return User::query()
            ->inTenant(Tenant::query()->findOrFail($session->tenant_id))
            ->where(fn (Builder $query) => $query
                ->whereLike('name', "%{$search}%")
                ->orWhereLike('national_code', "%{$search}%")
                ->orWhereLike('mobile', "%{$search}%"))
            ->orderBy('name')
            ->limit(50)
            ->pluck('name', 'id')
            ->all();
    }
}
