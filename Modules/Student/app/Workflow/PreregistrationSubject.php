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
use Modules\Student\Models\Preregistration;
use Modules\Student\Models\Program;
use Modules\Student\Models\Student;
use Modules\Student\Services\AdmitStudent;

/**
 * Admission as a workflow (ADR 0016, ADR 0018): a preregistration goes
 * through its program's workflow; approving the last step admits the
 * applicant as a learner of the chosen tenant.
 *
 * @extends WorkflowSubject<Preregistration>
 */
class PreregistrationSubject extends WorkflowSubject
{
    /** Attributes the «ویرایش اطلاعات» action may change, with their labels. */
    private const EDITABLE = [
        'program_id' => 'برنامه‌ی آموزشی',
        'program_title' => 'عنوان برنامه‌ی آموزشی',
        'first_name' => 'نام',
        'last_name' => 'نام خانوادگی',
        'father_name' => 'نام پدر',
        'mobile' => 'تلفن همراه',
        'phone' => 'تلفن ثابت',
        'email' => 'ایمیل',
        'postal_code' => 'کد پستی',
        'city' => 'شهر',
        'address' => 'نشانی',
    ];

    public function model(): string
    {
        return Preregistration::class;
    }

    public function label(): string
    {
        return 'پیش‌ثبت‌نام';
    }

    /**
     * @param  Preregistration  $subject
     */
    public function title(Model $subject): string
    {
        return "پیش‌ثبت‌نام {$subject->fullName()} ({$subject->program_title})";
    }

    public function completionSummary(): string
    {
        return 'متقاضی پذیرفته می‌شود: حساب کاربری (یا حساب موجود با همان کد ملی)، نقش‌ها در '.config('sarasystem.tenant_label').' انتخاب‌شده و ردیف «'.config('sarasystem.learner_plural_label').'».';
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
            Select::make('program_id')
                ->label('برنامه‌ی آموزشی')
                ->options(fn (WorkflowCase $record): array => Program::withoutTenantScope()
                    ->where('tenant_id', $record->subject?->tenant_id)
                    ->ordered()
                    ->pluck('title', 'id')
                    ->all())
                ->required()
                // Its form decides which fields are required and asked below.
                ->live(),
            Grid::make(3)->schema([
                TextInput::make('first_name')->label('نام')->required()->maxLength(50),
                TextInput::make('last_name')->label('نام خانوادگی')->required()->maxLength(50),
                TextInput::make('father_name')->label('نام پدر')->required(self::requires('father_name'))->maxLength(50),
                TextInput::make('mobile')->label('تلفن همراه')->required()->rule(new IranianMobile),
                TextInput::make('phone')->label('تلفن ثابت')->required(self::requires('phone'))->rule('nullable')->rule('digits:11'),
                TextInput::make('email')->label('ایمیل')->required(self::requires('email'))->email()->maxLength(100),
                TextInput::make('postal_code')->label('کد پستی')->required(self::requires('postal_code'))->rule('nullable')->rule('digits:10'),
                TextInput::make('city')->label('شهر')->required(self::requires('city'))->maxLength(100),
            ]),
            Textarea::make('address')->label('نشانی')->required(self::requires('address'))->minLength(10)->maxLength(300)->rows(2),
            Section::make('اطلاعات تکمیلی')
                ->columns(2)
                ->schema(fn (WorkflowCase $record, Get $get): array => CustomFieldInputs::components(self::formOf($record, $get('program_id'))))
                ->visible(fn (WorkflowCase $record, Get $get): bool => self::formOf($record, $get('program_id'))->fields !== []),
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
     * Whether the form, as set up for the preregistration's program, requires
     * the field (ADR 0017).
     *
     * @return Closure(WorkflowCase, Get): bool
     */
    private static function requires(string $field): Closure
    {
        return fn (WorkflowCase $record, Get $get): bool => self::formOf($record, $get('program_id'))->requires($field);
    }

    /**
     * The form as set up for the program chosen in the edit form.
     */
    private static function formOf(WorkflowCase $record, mixed $programId): ResolvedForm
    {
        /** @var Preregistration $preregistration */
        $preregistration = $record->subject;

        return self::formFor($preregistration, $programId);
    }

    private static function formFor(Preregistration $preregistration, mixed $programId): ResolvedForm
    {
        // The case may be open in another tenant's panel than the preregistration's.
        return PreregistrationForm::for(
            Tenant::query()->findOrFail($preregistration->tenant_id),
            filled($programId)
                ? Program::withoutTenantScope()->where('tenant_id', $preregistration->tenant_id)->find($programId)
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

        if (isset($data['program_id']) && (int) $data['program_id'] !== $subject->program_id) {
            $program = Program::withoutTenantScope()->where('tenant_id', $subject->tenant_id)->findOrFail($data['program_id']);
            $data['program_title'] = $program->title;
        }

        $form = self::formFor($subject, $data['program_id'] ?? $subject->program_id);
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
     * Where the learner is admitted, and with which roles.
     *
     * @param  Preregistration  $subject
     */
    public function completionForm(Model $subject): array
    {
        $existingUser = User::query()->where('national_code', $subject->national_code)->first();
        $tenantLabel = config('sarasystem.tenant_label');
        $learnerLabel = config('sarasystem.learner_label');
        $learnersLabel = config('sarasystem.learner_plural_label');

        return [
            Section::make('پذیرش')
                ->description($existingUser === null
                    ? "برای {$learnerLabel} یک حساب کاربری ساخته می‌شود؛ با موبایل ثبت‌شده و کد پیامکی وارد سامانه می‌شود."
                    : "کاربری با این کد ملی از قبل هست ({$existingUser->name})؛ نقش‌ها و ثبت‌نام به همان حساب داده می‌شود.")
                ->columns(2)
                ->schema([
                    Select::make('tenant_id')
                        ->label($tenantLabel)
                        ->options(fn (): array => Tenant::query()->active()->orderBy('name')->pluck('name', 'id')->all())
                        ->default($subject->preferred_tenant_id)
                        ->required()
                        ->live(),
                    DatePicker::make('enrolled_at')
                        ->label('تاریخ ثبت‌نام')
                        ->jalali()
                        ->default(now())
                        ->required(),
                    TextInput::make('student_code')
                        ->label("کد {$learnerLabel}")
                        ->maxLength(20)
                        // A rule, not ->unique(): the form's record is the workflow case.
                        ->rule(Rule::unique((new Student)->getTable(), 'student_code')),
                    CheckboxList::make('role_ids')
                        ->label("نقش‌ها در {$tenantLabel}")
                        ->helperText('فقط نقش‌هایی که خودتان اجازه‌ی دادنشان را دارید.')
                        ->options(fn (Get $get): array => self::roleOptions($get('tenant_id')))
                        ->default(fn (): array => Role::query()->whereNull('tenant_id')->where('name', AdmitStudent::STUDENT_ROLE)->pluck('id')->all())
                        ->columns(3)
                        ->columnSpanFull(),
                    Text::make("چیزی را که با این تأیید ساخته می‌شود بعداً در «{$learnersLabel}» همان {$tenantLabel} می‌بینید. افزودن به کلاس‌ها جداست.")
                        ->columnSpanFull(),
                ]),
        ];
    }

    /**
     * Roles the current user may give in the tenant, except the panel and
     * super admin roles: learners use the user panel.
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
