<?php

namespace Modules\Exam\Filament\Resources\Exams\Schemas;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Exam\Enums\ResultVisibility;
use Modules\Exam\Enums\RetakePolicy;
use Modules\Exam\Models\Exam;
use Modules\Exam\Models\ExamGroup;
use Modules\Exam\Models\Question;
use Modules\Exam\Models\QuestionBank;

class ExamForm
{
    public static function configure(
        Schema $schema,
        bool $forSession = false,
        bool $formalSession = false,
        bool $forStandaloneCreation = false,
    ): Schema {
        $graded = fn (Get $get, ?Exam $record): bool => $formalSession || ! ($record?->is_practice ?? $get('is_practice'));

        $practice = Toggle::make('is_practice')
            ->label('آزمون تمرینی')
            ->hidden($formalSession)
            ->helperText('شرکت مکرر بدون ثبت نمره، با امکان مشاهدهٔ پاسخ درست')
            ->default(false)
            ->disabledOn('edit')
            ->live()
            ->columnSpan($forStandaloneCreation ? 1 : 'full');

        $availability = [
            DateTimePicker::make('starts_at')
                ->label($forStandaloneCreation ? 'شروع دسترسی' : 'از')
                ->hidden($forSession)
                ->helperText($forStandaloneCreation ? 'خالی بگذارید تا پس از انتشار در دسترس باشد.' : 'خالی: از همین حالا')
                ->jalali()
                ->seconds(false),
            DateTimePicker::make('ends_at')
                ->label($forStandaloneCreation ? 'پایان دسترسی' : 'تا')
                ->hidden($forSession)
                ->helperText(fn (?Exam $record): string => $record?->allow_late_start
                    ? 'امکان شروع پس از پایان جلسه فعال است. این تاریخ فقط پس از غیرفعال کردن آن گزینه اعمال می‌شود.'
                    : 'خالی: بدون پایان. آزمونی که در جریان است در این زمان بسته می‌شود.')
                ->jalali()
                ->seconds(false)
                ->after('starts_at'),
            Toggle::make('is_published')
                ->label($forStandaloneCreation ? 'انتشار آزمون' : 'منتشر شده')
                ->helperText('فقط آزمون منتشرشده به شرکت‌کنندگان نشان داده می‌شود.')
                ->columnSpan($forStandaloneCreation ? 'full' : 1),
        ];

        if ($forStandaloneCreation) {
            $schema->columns(['lg' => 1, 'xl' => 12]);
        }

        return $schema
            ->components([
                Section::make($forStandaloneCreation ? 'مشخصات آزمون' : 'مشخصات')
                    ->icon($forStandaloneCreation ? Heroicon::OutlinedClipboardDocumentList : null)
                    ->compact($forStandaloneCreation)
                    ->columnSpan($forStandaloneCreation ? ['default' => 'full', 'xl' => 7] : 1)
                    ->columns($forStandaloneCreation ? ['sm' => 2] : 2)
                    ->schema([
                        TextInput::make('title')
                            ->label('عنوان')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('code')
                            ->label($forStandaloneCreation ? 'کد آزمون' : 'کد')
                            ->trim()
                            ->maxLength(64)
                            ->unique(table: Exam::class, ignoreRecord: true)
                            ->helperText($forStandaloneCreation ? 'خالی بماند، خودکار ساخته می‌شود.' : 'در صورت خالی بودن، به‌صورت خودکار ساخته می‌شود.')
                            ->extraInputAttributes(['dir' => 'ltr']),
                        ...($forStandaloneCreation ? [] : [$practice]),
                        Select::make('exam_group_id')
                            ->label('گروه آزمون')
                            ->relationship('examGroup', 'title')
                            ->searchable()
                            ->preload()
                            ->placeholder('بدون گروه')
                            ->rules(['bail', 'integer'])
                            ->scopedExists(ExamGroup::class, 'id'),
                        Textarea::make('description')
                            ->label('توضیحات')
                            ->helperText('پیش از شروع آزمون به شرکت‌کننده نشان داده می‌شود.')
                            ->rows($forStandaloneCreation ? 2 : 3)
                            ->columnSpan($forStandaloneCreation ? 1 : 'full'),
                        ...($forStandaloneCreation ? [$practice] : $availability),
                    ]),
                ...($forStandaloneCreation ? [
                    Section::make('زمان‌بندی و انتشار')
                        ->icon(Heroicon::OutlinedCalendarDays)
                        ->compact()
                        ->columnSpan(['default' => 'full', 'xl' => 5])
                        ->columns(['sm' => 2, 'xl' => 1])
                        ->schema($availability),
                ] : []),
                Section::make('سؤال‌ها')
                    ->icon($forStandaloneCreation ? Heroicon::OutlinedQueueList : null)
                    ->compact($forStandaloneCreation)
                    ->columnSpan($forStandaloneCreation ? 'full' : 1)
                    ->hidden(fn (?Exam $record): bool => $record?->question_snapshot !== null)
                    ->description($forStandaloneCreation
                        ? 'سؤال‌ها هنگام شروع از بانک برداشته می‌شوند. برای گلچین دستی، «انتخاب سؤال‌ها» را بزنید.'
                        : 'برای گلچین کردن سؤال‌های هر بانک، «انتخاب سؤال‌ها» را بزنید. در ردیف‌های بدون انتخاب دستی، سؤال‌ها هنگام شروع آزمون از بانک برداشته می‌شوند.')
                    ->schema([
                        Repeater::make('sections')
                            ->hiddenLabel()
                            ->relationship()
                            ->orderColumn('sort')
                            ->extraItemActions([self::selectQuestionsAction()])
                            ->schema([
                                Select::make('question_bank_id')
                                    ->label('بانک سؤال')
                                    ->options(fn (): array => QuestionBank::query()->withCount(['questions' => fn (Builder $query) => $query->where('is_active', true)])
                                        ->orderBy('title')->get()
                                        ->mapWithKeys(fn (QuestionBank $bank): array => [$bank->id => "{$bank->title} ({$bank->questions_count} سؤال فعال)"])
                                        ->all())
                                    ->in(fn (): array => QuestionBank::query()->pluck('id')->all())
                                    ->live()
                                    ->afterStateUpdated(fn (Set $set) => $set('question_ids', null))
                                    ->required()
                                    ->columnSpan($forStandaloneCreation ? ['sm' => 3] : 2),
                                TextInput::make('question_count')
                                    ->label('تعداد سؤال')
                                    ->integer()
                                    ->minValue(1)
                                    ->maxValue(500)
                                    ->default(5)
                                    ->readOnly(fn (Get $get): bool => filled($get('question_ids')))
                                    ->helperText(fn (Get $get): ?string => filled($get('question_ids')) ? 'انتخاب دستی' : null)
                                    ->rules([fn (Get $get): Closure => self::validateSelection($get)])
                                    ->required(),
                                Hidden::make('question_ids'),
                            ])
                            ->columns($forStandaloneCreation ? ['sm' => 4] : 3)
                            ->defaultItems(1)
                            ->addActionLabel('افزودن بانک')
                            ->addActionAlignment($forStandaloneCreation ? Alignment::Start : null),
                    ]),
                Section::make($forStandaloneCreation ? 'تنظیمات آزمون' : 'قوانین')
                    ->icon($forStandaloneCreation ? Heroicon::OutlinedAdjustmentsHorizontal : null)
                    ->compact($forStandaloneCreation)
                    ->columnSpan($forStandaloneCreation ? 'full' : 1)
                    ->columns($forStandaloneCreation ? ['sm' => 2, 'lg' => 3] : 3)
                    ->schema([
                        TextInput::make('duration_minutes')
                            ->label('مدت آزمون (دقیقه)')
                            ->integer()->minValue(1)->maxValue(1440)
                            ->default(30)
                            ->required(),
                        TextInput::make('grace_minutes')
                            ->label('زمان ارفاق (دقیقه)')
                            ->helperText('پس از پایان وقت، پاسخ‌ها تا این مدت پذیرفته می‌شوند (برای کندی اینترنت).')
                            ->integer()->minValue(0)->maxValue(60)
                            ->default(3)
                            ->required(),
                        TextInput::make('questions_per_page')
                            ->label('تعداد سؤال در هر صفحه')
                            ->integer()->minValue(1)->maxValue(100)
                            ->default(5)
                            ->required(),
                        TextInput::make('total_score')
                            ->label('نمره‌ی کل')
                            ->visible($graded)
                            ->numeric()->minValue(1)->maxValue(1000)
                            ->default(20)
                            ->required(),
                        TextInput::make('passing_score')
                            ->label('نمره‌ی قبولی')
                            ->visible($graded)
                            ->numeric()->minValue(0)
                            ->default(12)
                            ->lte('total_score')
                            ->required(),
                        Select::make('negative_marking')
                            ->label('نمره‌ی منفی')
                            ->visible($graded)
                            ->options(collect([2, 3, 4, 5, 6])->mapWithKeys(fn (int $count): array => [$count => "هر {$count} پاسخ غلط، یک پاسخ درست را حذف کند"])->all())
                            ->placeholder('بدون نمره‌ی منفی'),
                        TextInput::make('max_attempts')
                            ->label('تعداد دفعات مجاز شرکت')
                            ->visible($graded)
                            ->integer()->minValue(1)->maxValue(100)
                            ->default(1)
                            ->required(),
                        Select::make('retake_policy')
                            ->label('شرکت دوباره')
                            ->visible($graded)
                            ->options(RetakePolicy::class)
                            ->default(RetakePolicy::Always)
                            ->selectablePlaceholder(false)
                            ->required(),
                        Select::make('result_visibility')
                            ->label('نمایش نتیجه به شرکت‌کننده')
                            ->visible($graded)
                            ->options(ResultVisibility::class)
                            ->default(ResultVisibility::Hidden)
                            ->selectablePlaceholder(false)
                            ->required(),
                        Toggle::make('shuffle_questions')
                            ->label('سؤال‌ها تصادفی انتخاب و چیده شوند'),
                        Toggle::make('shuffle_options')
                            ->label('ترتیب گزینه‌ها تصادفی باشد'),
                    ]),
                Section::make('شرکت‌کنندگان')
                    ->icon($forStandaloneCreation ? Heroicon::OutlinedUsers : null)
                    ->compact($forStandaloneCreation)
                    ->columnSpan($forStandaloneCreation ? 'full' : 1)
                    ->hidden($forSession)
                    ->description(fn (?Exam $record): string => $record?->context_type !== null ? 'شرکت‌کنندگان این آزمون از عضویت در جلسه تعیین می‌شوند؛ انتخاب کاربر یا نقش این‌جا کاربرد ندارد.' : 'آزمون را این کاربران و دارندگان این نقش‌ها (در همین '.config('sarasystem.tenant_label').') می‌بینند.')
                    ->columns(2)
                    ->schema([
                        Select::make('participantUsers')
                            ->hidden(fn (?Exam $record): bool => $record?->context_type !== null)
                            ->label('کاربران')
                            ->relationship('participantUsers', 'name', fn (Builder $query) => $query->inTenant(self::tenant()))
                            ->getOptionLabelFromRecordUsing(fn (User $record): string => $record->mobile ? "{$record->name} ({$record->mobile})" : $record->name)
                            ->searchable(['name', 'mobile', 'national_code'])
                            ->multiple()
                            ->rules([fn (): Closure => self::usersOfCurrentTenant()]),
                        Select::make('participantRoles')
                            ->hidden(fn (?Exam $record): bool => $record?->context_type !== null)
                            ->label('نقش‌ها')
                            ->relationship('participantRoles', 'name', fn (Builder $query) => self::participantRoleQuery($query))
                            ->getOptionLabelFromRecordUsing(fn (Role $record): string => $record->display_name)
                            ->multiple()
                            ->preload()
                            ->rules([fn (): Closure => self::rolesOfCurrentTenant()]),
                    ]),
            ]);
    }

    private static function selectQuestionsAction(): Action
    {
        return Action::make('selectQuestions')
            ->label('انتخاب سؤال‌ها')
            ->icon(Heroicon::OutlinedListBullet)
            ->link()
            ->visible(fn (array $arguments, Repeater $component): bool => filled($component->getRawState()[$arguments['item'] ?? '']['question_bank_id'] ?? null))
            ->modalHeading('انتخاب سؤال‌های بانک')
            ->modalSubmitActionLabel('ثبت انتخاب')
            ->modalCancelActionLabel('انصراف')
            ->schema(fn (array $arguments, Repeater $component): array => [
                CheckboxList::make('question_ids')
                    ->label('سؤال‌های فعال')
                    ->helperText('برای برگشت به برداشت خودکار، همهٔ تیک‌ها را بردارید و انتخاب را ثبت کنید.')
                    ->options(fn (): array => self::selectableQuestions($component->getRawState(), $arguments['item'])
                        ?->orderBy('exam_questions.id')
                        ->get(['exam_questions.id', 'exam_questions.body', 'exam_questions.type', 'exam_questions.score'])
                        ->mapWithKeys(fn (Question $question): array => [
                            $question->id => "{$question->body} ({$question->type->getLabel()}، {$question->score} نمره)",
                        ])->all() ?? [])
                    ->searchable()
                    ->noSearchResultsMessage('سؤالی پیدا نشد.')
                    ->rules(['array', 'list', 'max:500']),
            ])
            ->fillForm(function (array $arguments, Repeater $component): array {
                $item = $component->getRawState()[$arguments['item']] ?? [];
                $count = max(1, min(500, (int) ($item['question_count'] ?? 1)));

                return ['question_ids' => $item['question_ids']
                    ?? self::selectableQuestions($component->getRawState(), $arguments['item'])
                        ?->inRandomOrder()->limit($count)->pluck('exam_questions.id')->all()
                    ?? []];
            })
            ->action(function (array $data, array $arguments, Repeater $component): void {
                $state = $component->getRawState();
                $item = $arguments['item'];
                $ids = array_map('intval', $data['question_ids'] ?? []);
                $available = self::selectableQuestions($state, $item);

                if ($available === null || ($ids !== [] && $available->whereKey($ids)->count() !== count($ids))) {
                    throw ValidationException::withMessages(['question_ids' => 'سؤال‌های فعال و غیرتکراری همین بانک را انتخاب کنید.']);
                }

                $state[$item]['question_ids'] = $ids ?: null;
                if ($ids !== []) {
                    $state[$item]['question_count'] = count($ids);
                }
                $component->rawState($state);
            });
    }

    /** @return BelongsToMany<Question, QuestionBank>|null */
    private static function selectableQuestions(array $sections, string $item): ?BelongsToMany
    {
        $reserved = collect($sections)->except($item)->pluck('question_ids')->flatten()
            ->filter(fn (mixed $id): bool => filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false)
            ->all();

        return self::findBank($sections[$item]['question_bank_id'] ?? null)?->questions()->active()
            ->whereNotIn('exam_questions.id', $reserved);
    }

    private static function validateSelection(Get $get): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($get): void {
            $ids = $get('question_ids');
            if ($ids === null) {
                return;
            }

            $validator = Validator::make(['ids' => $ids], [
                'ids' => ['required', 'array', 'list', 'size:'.(int) $value],
                'ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            ]);
            $bank = self::findBank($get('question_bank_id'));

            if ($validator->fails() || $bank?->questions()->active()->whereKey($ids)->count() !== count($ids)) {
                $fail('سؤال‌های انتخاب‌شده را بررسی کنید. همه باید فعال و متعلق به همین بانک باشند.');

                return;
            }

            $item = str($attribute)->beforeLast('.')->afterLast('.')->toString();
            foreach ($get('../') ?? [] as $key => $section) {
                if ((string) $key !== $item && array_intersect($ids, (array) ($section['question_ids'] ?? [])) !== []) {
                    $fail('هر سؤال را فقط در یکی از ردیف‌های آزمون انتخاب کنید.');

                    return;
                }
            }
        };
    }

    private static function findBank(mixed $id): ?QuestionBank
    {
        $id = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $id === false ? null : QuestionBank::query()->find($id);
    }

    private static function tenant(): Tenant
    {
        /** @var Tenant */
        return Filament::getTenant();
    }

    /**
     * Roles that can be held in this tenant, apart from super admin.
     *
     * @param  Builder<Role>  $query
     * @return Builder<Role>
     */
    private static function participantRoleQuery(Builder $query): Builder
    {
        return $query->assignableIn(self::tenant())->where('name', '!=', Role::SUPER_ADMIN);
    }

    private static function usersOfCurrentTenant(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $ids = array_unique(array_map('intval', (array) $value));

            if (User::query()->whereKey($ids)->inTenant(self::tenant())->count() !== count($ids)) {
                $fail('کاربر انتخاب‌شده عضو این '.config('sarasystem.tenant_label').' نیست.');
            }
        };
    }

    private static function rolesOfCurrentTenant(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $ids = array_unique(array_map('intval', (array) $value));

            if (self::participantRoleQuery(Role::query()->whereKey($ids))->count() !== count($ids)) {
                $fail('نقش انتخاب‌شده معتبر نیست.');
            }
        };
    }
}
