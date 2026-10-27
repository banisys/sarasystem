<?php

namespace Modules\Student\Workflow;

use App\Authorization\RoleAssignment;
use App\CustomFields\Answers;
use App\CustomFields\ResolvedForm;
use App\Filament\CustomFields\CustomFieldInputs;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkflowCase;
use App\Rules\IranianMobile;
use App\Workflow\WorkflowSubject;
use BezhanSalleh\FilamentShield\Support\Utils;
use Closure;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Modules\Student\Enums\PreregistrationStatus;
use Modules\Student\Filament\Resources\Preregistrations\Schemas\PreregistrationInfolist;
use Modules\Student\Forms\PreregistrationForm;
use Modules\Student\Models\Grade;
use Modules\Student\Models\Preregistration;
use Modules\Student\Models\Student;
use Modules\Student\Services\AdmitStudent;

/**
 * Admission as a workflow (ADR 0016, ADR 0018): a preregistration goes
 * through its stage's workflow; approving the last step admits the student.
 *
 * @extends WorkflowSubject<Preregistration>
 */
class PreregistrationSubject extends WorkflowSubject
{
    /** Attributes the «ویرایش اطلاعات» action may change, with their labels. */
    private const EDITABLE = [
        'grade_id' => 'پایه',
        'grade_title' => 'مقطع و پایه',
        'first_name' => 'نام',
        'last_name' => 'نام خانوادگی',
        'father_name' => 'نام پدر',
        'birth_certificate_number' => 'شماره شناسنامه',
        'birth_place' => 'محل تولد',
        'mobile' => 'تلفن همراه',
        'phone' => 'تلفن منزل',
        'email' => 'ایمیل',
        'postal_code' => 'کد پستی',
        'city' => 'شهر',
        'address' => 'نشانی منزل',
        'previous_school' => 'نام مدرسه‌ی پیشین',
        'last_average' => 'معدل ترم آخر',
    ];

    public function model(): string
    {
        return Preregistration::class;
    }

    public function label(): string
    {
        return 'پیش‌ثبت‌نام دانش‌آموز';
    }

    /**
     * @param  Preregistration  $subject
     */
    public function title(Model $subject): string
    {
        return "پیش‌ثبت‌نام {$subject->fullName()} ({$subject->grade_title})";
    }

    public function completionSummary(): string
    {
        return 'دانش‌آموز پذیرفته می‌شود: حساب کاربری (یا حساب موجود با همان کد ملی)، نقش‌ها در '.config('sarasystem.tenant_label').' محل تحصیل و ردیف «دانش‌آموزان».';
    }

    /**
     * Preregistrations belong to the primary tenant (ADR 0018), so their
     * workflows are designed there.
     */
    public function availableIn(Tenant $tenant): bool
    {
        return (bool) $tenant->is_primary;
    }

    public function details(): array
    {
        return PreregistrationInfolist::components();
    }

    public function editForm(): array
    {
        return [
            Select::make('grade_id')
                ->label('پایه')
                ->options(fn (WorkflowCase $record): array => Grade::selectOptions($record->subject->tenant_id))
                ->required()
                // Its stage's form decides which fields are required and asked below.
                ->live(),
            Grid::make(3)->schema([
                TextInput::make('first_name')->label('نام')->required()->maxLength(50),
                TextInput::make('last_name')->label('نام خانوادگی')->required()->maxLength(50),
                TextInput::make('father_name')->label('نام پدر')->required(self::requires('father_name'))->maxLength(50),
                TextInput::make('birth_certificate_number')->label('شماره شناسنامه')->required(self::requires('birth_certificate_number'))->maxLength(50),
                TextInput::make('birth_place')->label('محل تولد')->required(self::requires('birth_place'))->maxLength(100),
                TextInput::make('mobile')->label('تلفن همراه')->required()->rule(new IranianMobile),
                TextInput::make('phone')->label('تلفن منزل')->required(self::requires('phone'))->rule('nullable')->rule('digits:11'),
                TextInput::make('email')->label('ایمیل')->required(self::requires('email'))->email()->maxLength(100),
                TextInput::make('postal_code')->label('کد پستی')->required(self::requires('postal_code'))->rule('nullable')->rule('digits:10'),
                TextInput::make('city')->label('شهر')->required(self::requires('city'))->maxLength(100),
                TextInput::make('previous_school')->label('نام مدرسه‌ی پیشین')->required(self::requires('previous_school'))->maxLength(150),
                TextInput::make('last_average')->label('معدل ترم آخر')->required(self::requires('last_average'))->numeric()->minValue(0)->maxValue(20),
            ]),
            Textarea::make('address')->label('نشانی منزل')->required(self::requires('address'))->minLength(10)->maxLength(300)->rows(2),
            Section::make('اطلاعات تکمیلی')
                ->columns(2)
                ->schema(fn (WorkflowCase $record, Get $get): array => CustomFieldInputs::components(self::formOf($record, $get('grade_id'))))
                ->visible(fn (WorkflowCase $record, Get $get): bool => self::formOf($record, $get('grade_id'))->fields !== []),
        ];
    }

    /**
     * @param  Preregistration  $subject
     */
    public function editData(Model $subject): array
    {
        return [...$subject->attributesToArray(), ResolvedForm::INPUT => CustomFieldInputs::state($subject->custom_fields)];
    }

    /**
     * Whether the form, as set up for the preregistration's stage, requires
     * the field (ADR 0017).
     *
     * @return Closure(WorkflowCase, Get): bool
     */
    private static function requires(string $field): Closure
    {
        return fn (WorkflowCase $record, Get $get): bool => self::formOf($record, $get('grade_id'))->requires($field);
    }

    /**
     * The form as set up for the stage of the grade chosen in the edit form.
     */
    private static function formOf(WorkflowCase $record, mixed $gradeId): ResolvedForm
    {
        /** @var Preregistration $preregistration */
        $preregistration = $record->subject;

        return self::formFor($preregistration, $gradeId);
    }

    private static function formFor(Preregistration $preregistration, mixed $gradeId): ResolvedForm
    {
        // The case may be open in another tenant's panel than the preregistration's.
        return PreregistrationForm::for(
            Tenant::query()->findOrFail($preregistration->tenant_id),
            filled($gradeId)
                ? Grade::withoutTenantScope()->where('tenant_id', $preregistration->tenant_id)->find($gradeId)
                : null,
        );
    }

    /**
     * @param  Preregistration  $subject
     */
    public function saveEdit(Model $subject, array $data): void
    {
        $custom = (array) ($data[ResolvedForm::INPUT] ?? []);
        $data = array_intersect_key($data, self::EDITABLE);

        if (isset($data['grade_id']) && (int) $data['grade_id'] !== $subject->grade_id) {
            $grade = Grade::withoutTenantScope()->where('tenant_id', $subject->tenant_id)->findOrFail($data['grade_id']);
            $data['grade_title'] = $grade->fullTitle();
        }

        $form = self::formFor($subject, $data['grade_id'] ?? $subject->grade_id);
        $data['custom_fields'] = CustomFieldInputs::answers($form, $custom, $subject->custom_fields);

        $subject->fill($data)->save();
    }

    /**
     * @param  Preregistration  $subject
     */
    public function changes(Model $subject, array $before): array
    {
        $changes = parent::changes($subject, $before);

        // One entry per changed answer instead of the whole list.
        unset($changes['custom_fields']);

        if ($subject->wasChanged('custom_fields')) {
            $changes += Answers::changes(json_decode($before['custom_fields'] ?? 'null', true), $subject->custom_fields);
        }

        return $changes;
    }

    public function attributeLabel(string $attribute): string
    {
        return self::EDITABLE[$attribute] ?? $attribute;
    }

    /**
     * Where and in which grade the student is admitted.
     *
     * @param  Preregistration  $subject
     */
    public function completionForm(Model $subject): array
    {
        $existingUser = User::query()->where('national_code', $subject->national_code)->first();
        $tenantLabel = config('sarasystem.tenant_label');

        return [
            Section::make('پذیرش')
                ->description($existingUser === null
                    ? 'برای دانش‌آموز یک حساب کاربری ساخته می‌شود؛ با موبایل ثبت‌شده و کد پیامکی وارد سامانه می‌شود.'
                    : "کاربری با این کد ملی از قبل هست ({$existingUser->name})؛ نقش‌ها و ثبت‌نام به همان حساب داده می‌شود.")
                ->columns(2)
                ->schema([
                    Select::make('tenant_id')
                        ->label("{$tenantLabel} محل تحصیل")
                        ->options(fn (): array => Tenant::query()->active()->orderBy('name')->pluck('name', 'id')->all())
                        ->default($subject->preferred_tenant_id)
                        ->required()
                        ->live(),
                    Select::make('grade_id')
                        ->label('پایه')
                        ->options(fn (): array => Grade::selectOptions($subject->tenant_id))
                        ->default($subject->grade_id)
                        ->required(),
                    DatePicker::make('enrolled_at')
                        ->label('تاریخ ثبت‌نام')
                        ->jalali()
                        ->default(now())
                        ->required(),
                    TextInput::make('student_code')
                        ->label('کد دانش‌آموزی')
                        ->maxLength(20)
                        // A rule, not ->unique(): the form's record is the workflow case.
                        ->rule(Rule::unique((new Student)->getTable(), 'student_code')),
                    CheckboxList::make('role_ids')
                        ->label("نقش‌ها در {$tenantLabel} محل تحصیل")
                        ->helperText('فقط نقش‌هایی که خودتان اجازه‌ی دادنشان را دارید.')
                        ->options(fn (Get $get): array => self::roleOptions($get('tenant_id')))
                        ->default(fn (): array => Role::query()->whereNull('tenant_id')->where('name', AdmitStudent::STUDENT_ROLE)->pluck('id')->all())
                        ->columns(3)
                        ->columnSpanFull(),
                    Text::make('چیزی را که با این تأیید ساخته می‌شود بعداً در «دانش‌آموزان» همان '.$tenantLabel.' می‌بینید.')
                        ->columnSpanFull(),
                ]),
        ];
    }

    /**
     * Roles the current user may give in the tenant, except the panel and
     * super admin roles: students use the user panel.
     *
     * @return array<int, string>
     */
    private static function roleOptions(mixed $tenantId): array
    {
        $tenant = filled($tenantId) ? Tenant::query()->find($tenantId) : null;
        $user = auth()->user();

        if ($tenant === null || ! $user instanceof User) {
            return [];
        }

        return RoleAssignment::assignableRoles($user, $tenant)
            ->reject(fn (Role $role): bool => $role->name === Utils::getPanelUserRoleName() || $role->name === Role::SUPER_ADMIN)
            ->mapWithKeys(fn (Role $role): array => [$role->getKey() => $role->display_name])
            ->all();
    }

    /**
     * Submitted while at the first step, in review after it.
     */
    public function moved(WorkflowCase $case): void
    {
        /** @var Preregistration $preregistration */
        $preregistration = $case->subject;

        if (! $preregistration->status->isClosed()) {
            $preregistration->update(['status' => $case->current_step > 0 ? PreregistrationStatus::InReview : PreregistrationStatus::Submitted]);
        }
    }

    public function completed(WorkflowCase $case, User $actor, array $data): void
    {
        /** @var Preregistration $preregistration */
        $preregistration = $case->subject;

        app(AdmitStudent::class)->admit($preregistration, $actor, $data);
    }

    public function rejected(WorkflowCase $case, User $actor): void
    {
        $case->subject->update(['status' => PreregistrationStatus::Rejected]);
    }
}
