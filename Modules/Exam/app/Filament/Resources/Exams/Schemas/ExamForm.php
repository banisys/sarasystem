<?php

namespace Modules\Exam\Filament\Resources\Exams\Schemas;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Modules\Exam\Enums\ResultVisibility;
use Modules\Exam\Enums\RetakePolicy;
use Modules\Exam\Models\QuestionBank;

class ExamForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('مشخصات')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->label('عنوان')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label('توضیحات')
                            ->helperText('پیش از شروع آزمون به شرکت‌کننده نشان داده می‌شود.')
                            ->rows(3)
                            ->columnSpanFull(),
                        DateTimePicker::make('starts_at')
                            ->label('از')
                            ->helperText('خالی: از همین حالا')
                            ->jalali()
                            ->seconds(false),
                        DateTimePicker::make('ends_at')
                            ->label('تا')
                            ->helperText('خالی: بدون پایان. آزمونی که در جریان است در این زمان بسته می‌شود.')
                            ->jalali()
                            ->seconds(false)
                            ->after('starts_at'),
                        Toggle::make('is_published')
                            ->label('منتشر شده')
                            ->helperText('فقط آزمون منتشرشده به شرکت‌کنندگان نشان داده می‌شود.'),
                    ]),
                Section::make('سؤال‌ها')
                    ->description('هنگام شروع هر آزمون، از هر بانک به تعداد گفته‌شده سؤال فعال برداشته می‌شود.')
                    ->schema([
                        Repeater::make('sections')
                            ->hiddenLabel()
                            ->relationship()
                            ->orderColumn('sort')
                            ->schema([
                                Select::make('question_bank_id')
                                    ->label('بانک سؤال')
                                    ->options(fn (): array => QuestionBank::query()->withCount(['questions' => fn (Builder $query) => $query->where('is_active', true)])
                                        ->orderBy('title')->get()
                                        ->mapWithKeys(fn (QuestionBank $bank): array => [$bank->id => "{$bank->title} ({$bank->questions_count} سؤال فعال)"])
                                        ->all())
                                    ->in(fn (): array => QuestionBank::query()->pluck('id')->all())
                                    ->required()
                                    ->columnSpan(2),
                                TextInput::make('question_count')
                                    ->label('تعداد سؤال')
                                    ->integer()
                                    ->minValue(1)
                                    ->maxValue(500)
                                    ->default(5)
                                    ->required(),
                            ])
                            ->columns(3)
                            ->defaultItems(1)
                            ->addActionLabel('افزودن بانک'),
                    ]),
                Section::make('قوانین')
                    ->columns(3)
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
                            ->numeric()->minValue(1)->maxValue(1000)
                            ->default(20)
                            ->required(),
                        TextInput::make('passing_score')
                            ->label('نمره‌ی قبولی')
                            ->numeric()->minValue(0)
                            ->default(12)
                            ->lte('total_score')
                            ->required(),
                        Select::make('negative_marking')
                            ->label('نمره‌ی منفی')
                            ->options(collect([2, 3, 4, 5, 6])->mapWithKeys(fn (int $count): array => [$count => "هر {$count} پاسخ غلط، یک پاسخ درست را حذف کند"])->all())
                            ->placeholder('بدون نمره‌ی منفی'),
                        TextInput::make('max_attempts')
                            ->label('تعداد دفعات مجاز شرکت')
                            ->integer()->minValue(1)->maxValue(100)
                            ->default(1)
                            ->required(),
                        Select::make('retake_policy')
                            ->label('شرکت دوباره')
                            ->options(RetakePolicy::class)
                            ->default(RetakePolicy::Always)
                            ->selectablePlaceholder(false)
                            ->required(),
                        Select::make('result_visibility')
                            ->label('نمایش نتیجه به شرکت‌کننده')
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
                    ->description('آزمون را این کاربران و دارندگان این نقش‌ها (در همین '.config('sarasystem.tenant_label').') می‌بینند.')
                    ->columns(2)
                    ->schema([
                        Select::make('participantUsers')
                            ->label('کاربران')
                            ->relationship('participantUsers', 'name', fn (Builder $query) => $query->inTenant(self::tenant()))
                            ->getOptionLabelFromRecordUsing(fn (User $record): string => $record->mobile ? "{$record->name} ({$record->mobile})" : $record->name)
                            ->searchable(['name', 'mobile', 'national_code'])
                            ->multiple()
                            ->rules([fn (): Closure => self::usersOfCurrentTenant()]),
                        Select::make('participantRoles')
                            ->label('نقش‌ها')
                            ->relationship('participantRoles', 'name', fn (Builder $query) => self::participantRoleQuery($query))
                            ->getOptionLabelFromRecordUsing(fn (Role $record): string => $record->display_name)
                            ->multiple()
                            ->preload()
                            ->rules([fn (): Closure => self::rolesOfCurrentTenant()]),
                    ]),
            ]);
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
