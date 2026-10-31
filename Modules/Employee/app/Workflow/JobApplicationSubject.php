<?php

namespace Modules\Employee\Workflow;

use App\CustomFields\Answers;
use App\CustomFields\ResolvedForm;
use App\Filament\CustomFields\CustomFieldInputs;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkflowCase;
use App\Rules\IranianMobile;
use App\Workflow\WorkflowSubject;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Modules\Employee\Enums\ApplicationStatus;
use Modules\Employee\Enums\EmploymentType;
use Modules\Employee\Filament\Resources\JobApplications\Schemas\JobApplicationInfolist;
use Modules\Employee\Forms\JobApplicationForm;
use Modules\Employee\Models\Employee;
use Modules\Employee\Models\JobApplication;
use Modules\Employee\Models\JobPosition;
use Modules\Employee\Services\HireApplicant;
use Modules\Employee\Support\EmployeeRoles;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Hiring as a workflow (ADR 0016): a job application goes through its job
 * position's workflow; approving the last step hires the applicant.
 *
 * @extends WorkflowSubject<JobApplication>
 */
class JobApplicationSubject extends WorkflowSubject
{
    /** Attributes the «ویرایش اطلاعات» action may change, with their labels. */
    private const EDITABLE = [
        'job_position_id' => 'شغل درخواستی',
        'job_title' => 'عنوان شغل',
        'first_name' => 'نام',
        'last_name' => 'نام خانوادگی',
        'father_name' => 'نام پدر',
        'mobile' => 'تلفن همراه',
        'phone' => 'تلفن ثابت',
        'email' => 'ایمیل',
        'postal_code' => 'کد پستی',
        'address' => 'نشانی',
    ];

    public function model(): string
    {
        return JobApplication::class;
    }

    public function label(): string
    {
        return 'درخواست همکاری';
    }

    /**
     * @param  JobApplication  $subject
     */
    public function title(Model $subject): string
    {
        return "درخواست همکاری {$subject->fullName()} ({$subject->job_title})";
    }

    public function completionSummary(): string
    {
        return 'متقاضی استخدام می‌شود: حساب کاربری (یا حساب موجود با همان کد ملی)، نقش‌ها در '.config('sarasystem.tenant_label').' محل خدمت و ردیف «کارمندان».';
    }

    public function details(): array
    {
        return JobApplicationInfolist::components();
    }

    public function extraActions(): array
    {
        return [
            Action::make('downloadResume')
                ->label('دریافت رزومه')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->visible(fn (WorkflowCase $record): bool => $record->subject instanceof JobApplication && $record->subject->hasMedia(JobApplication::RESUME_COLLECTION))
                ->action(function (WorkflowCase $record): BinaryFileResponse {
                    /** @var JobApplication $application */
                    $application = $record->subject;

                    return response()->download($application->getFirstMedia(JobApplication::RESUME_COLLECTION)->getPath(), "resume-{$application->national_code}.pdf");
                }),
        ];
    }

    public function editForm(): array
    {
        return [
            Select::make('job_position_id')
                ->label('شغل درخواستی')
                ->options(fn (WorkflowCase $record): array => JobPosition::withoutTenantScope()
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
            ]),
            Textarea::make('address')->label('نشانی')->required(self::requires('address'))->minLength(10)->maxLength(300)->rows(2),
            Section::make('اطلاعات تکمیلی')
                ->columns(2)
                ->schema(fn (WorkflowCase $record, Get $get): array => CustomFieldInputs::components(self::formOf($record, $get('job_position_id'))))
                ->visible(fn (WorkflowCase $record, Get $get): bool => self::formOf($record, $get('job_position_id'))->fields !== []),
        ];
    }

    /**
     * @param  JobApplication  $subject
     */
    public function editData(Model $subject): array
    {
        return [...$subject->attributesToArray(), ResolvedForm::INPUT => CustomFieldInputs::state($subject->custom_fields)];
    }

    /**
     * Whether the form, as set up for the application's position, requires
     * the field (ADR 0017).
     *
     * @return Closure(WorkflowCase, Get): bool
     */
    private static function requires(string $field): Closure
    {
        return fn (WorkflowCase $record, Get $get): bool => self::formOf($record, $get('job_position_id'))->requires($field);
    }

    /**
     * The form as set up for the position chosen in the edit form.
     */
    private static function formOf(WorkflowCase $record, mixed $positionId): ResolvedForm
    {
        /** @var JobApplication $application */
        $application = $record->subject;

        return self::formFor($application, $positionId);
    }

    private static function formFor(JobApplication $application, mixed $positionId): ResolvedForm
    {
        // The case may be open in another tenant's panel than the application's.
        return JobApplicationForm::for(
            Tenant::query()->findOrFail($application->tenant_id),
            filled($positionId)
                ? JobPosition::withoutTenantScope()->where('tenant_id', $application->tenant_id)->find($positionId)
                : null,
        );
    }

    /**
     * @param  JobApplication  $subject
     */
    public function saveEdit(Model $subject, array $data): void
    {
        $custom = (array) ($data[ResolvedForm::INPUT] ?? []);
        $data = array_intersect_key($data, self::EDITABLE);

        if (isset($data['job_position_id']) && (int) $data['job_position_id'] !== $subject->job_position_id) {
            $position = JobPosition::withoutTenantScope()->where('tenant_id', $subject->tenant_id)->findOrFail($data['job_position_id']);
            $data['job_title'] = $position->title;
        }

        $form = self::formFor($subject, $data['job_position_id'] ?? $subject->job_position_id);
        $data['custom_fields'] = CustomFieldInputs::answers($form, $custom, $subject->custom_fields);

        $subject->fill($data)->save();
    }

    /**
     * @param  JobApplication  $subject
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
     * Where and how the applicant is hired.
     *
     * @param  JobApplication  $subject
     */
    public function completionForm(Model $subject): array
    {
        $existingUser = User::query()->where('national_code', $subject->national_code)->first();
        $tenantLabel = config('sarasystem.tenant_label');

        return [
            Section::make('استخدام')
                ->description($existingUser === null
                    ? 'برای متقاضی یک حساب کاربری ساخته می‌شود؛ با موبایل خود و کد پیامکی وارد سامانه می‌شود.'
                    : "کاربری با این کد ملی از قبل هست ({$existingUser->name})؛ نقش‌ها و استخدام به همان حساب داده می‌شود.")
                ->columns(2)
                ->schema([
                    Select::make('tenant_id')
                        ->label("{$tenantLabel} محل خدمت")
                        ->options(fn (): array => Tenant::query()->active()->orderBy('name')->pluck('name', 'id')->all())
                        ->default($subject->tenant_id)
                        ->required()
                        ->live(),
                    Select::make('employment_type')
                        ->label('نوع همکاری')
                        ->options(EmploymentType::class)
                        ->default(EmploymentType::FullTime->value)
                        ->required(),
                    DatePicker::make('hired_at')
                        ->label('تاریخ شروع به کار')
                        ->jalali()
                        ->default(now())
                        ->required(),
                    TextInput::make('personnel_code')
                        ->label('کد پرسنلی')
                        ->maxLength(20)
                        // A rule, not ->unique(): the form's record is the workflow case.
                        ->rule(Rule::unique((new Employee)->getTable(), 'personnel_code')),
                    CheckboxList::make('role_ids')
                        ->label("نقش‌ها در {$tenantLabel} محل خدمت")
                        ->helperText('فقط نقش‌هایی که خودتان اجازه‌ی دادنشان را دارید.')
                        ->options(fn (Get $get): array => self::roleOptions($get('tenant_id')))
                        ->columns(3)
                        ->columnSpanFull(),
                    Toggle::make('panel_access')
                        ->label('دسترسی به پنل مدیریت')
                        ->default(true),
                    Text::make('چیزی را که با این تأیید ساخته می‌شود بعداً در «کارمندان» همان '.$tenantLabel.' می‌بینید.')
                        ->columnSpanFull(),
                ]),
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function roleOptions(mixed $tenantId): array
    {
        $tenant = filled($tenantId) ? Tenant::query()->find($tenantId) : null;
        $user = auth()->user();

        if ($tenant === null || ! $user instanceof User) {
            return [];
        }

        return EmployeeRoles::options($user, $tenant);
    }

    /**
     * Submitted while at the first step, in review after it.
     */
    public function moved(WorkflowCase $case): void
    {
        /** @var JobApplication $application */
        $application = $case->subject;

        if (! $application->status->isClosed()) {
            $application->update(['status' => $case->current_step > 0 ? ApplicationStatus::InReview : ApplicationStatus::Submitted]);
        }
    }

    public function completed(WorkflowCase $case, User $actor, array $data): void
    {
        /** @var JobApplication $application */
        $application = $case->subject;

        app(HireApplicant::class)->hire($application, $actor, $data);
    }

    public function rejected(WorkflowCase $case, User $actor): void
    {
        $case->subject->update(['status' => ApplicationStatus::Rejected]);
    }
}
