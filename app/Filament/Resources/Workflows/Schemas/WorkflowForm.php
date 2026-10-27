<?php

namespace App\Filament\Resources\Workflows\Schemas;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Identifiers;
use App\Workflow\AssigneeType;
use App\Workflow\StepAction;
use App\Workflow\StepExtension;
use App\Workflow\WorkflowStepExtensions;
use App\Workflow\WorkflowSubjects;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

/**
 * A workflow and its steps (ADR 0016). Each step goes to a role or a
 * person in a tenant; approving is always possible, the other actions are
 * chosen per step.
 */
class WorkflowForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('فرایند')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('نام')
                            ->required()
                            ->maxLength(255),
                        Select::make('subject_type')
                            ->label('برای')
                            ->helperText('نوع پرونده‌هایی که این فرایند روی آن‌ها اجرا می‌شود؛ بعداً قابل تغییر نیست.')
                            ->options(fn (): array => self::subjectOptions())
                            ->required()
                            ->in(fn (): array => array_keys(self::subjectOptions()))
                            ->disabledOn('edit'),
                        Textarea::make('description')
                            ->label('توضیحات')
                            ->rows(2)
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label('فعال')
                            ->helperText('پرونده‌ی جدید فقط با فرایند فعال شروع می‌شود.')
                            ->default(true),
                    ]),
                Section::make('مرحله‌ها')
                    ->description('پرونده به ترتیب از این مرحله‌ها می‌گذرد. تغییر مرحله‌ها فقط روی پرونده‌هایی اثر دارد که از این به بعد شروع می‌شوند.')
                    ->schema([
                        Repeater::make('steps')
                            ->hiddenLabel()
                            ->minItems(1)
                            ->required()
                            ->addActionLabel('افزودن مرحله')
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                            ->columns(2)
                            ->schema(self::stepFields()),
                    ]),
            ])
            ->columns(1);
    }

    /**
     * @return array<int, Field|Group>
     */
    private static function stepFields(): array
    {
        return [
            TextInput::make('name')
                ->label('نام مرحله')
                ->placeholder('مثلاً: بررسی اولیه، مصاحبه، تأیید مدیر')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true),
            Select::make('tenant_id')
                ->label(config('sarasystem.tenant_label'))
                ->helperText('کارتابل این '.config('sarasystem.tenant_label').' پرونده را می‌گیرد.')
                ->options(fn (): array => Tenant::query()->active()->orderBy('name')->pluck('name', 'id')->all())
                ->default(fn (): ?int => Filament::getTenant()?->getKey())
                ->required()
                ->live(),
            ToggleButtons::make('assignee_type')
                ->label('مسئول')
                ->options(AssigneeType::class)
                ->default(AssigneeType::Role->value)
                ->inline()
                ->required()
                ->live(),
            Select::make('role_id')
                ->label('نقش')
                ->helperText('هر کدام از دارندگان این نقش تأیید کند کافی است.')
                ->options(fn (Get $get): array => self::roleOptions($get('tenant_id')))
                ->visible(fn (Get $get): bool => self::assigneeType($get) === AssigneeType::Role)
                ->required(fn (Get $get): bool => self::assigneeType($get) === AssigneeType::Role)
                ->in(fn (Get $get): array => array_keys(self::roleOptions($get('tenant_id')))),
            Select::make('user_id')
                ->label('شخص')
                ->helperText('از کاربران این '.config('sarasystem.tenant_label').'؛ با نام، موبایل یا کد ملی جست‌وجو کنید.')
                ->searchable()
                ->getSearchResultsUsing(fn (string $search, Get $get): array => self::userQuery($get('tenant_id'))
                    ->where(fn (Builder $query) => $query
                        ->where('name', 'ilike', '%'.$search.'%')
                        ->orWhere('mobile', 'like', '%'.Identifiers::toLatinDigits($search).'%')
                        ->orWhere('national_code', 'like', '%'.Identifiers::toLatinDigits($search).'%'))
                    ->orderBy('name')
                    ->limit(10)
                    ->pluck('name', 'id')
                    ->all())
                ->getOptionLabelUsing(fn (mixed $value): ?string => User::query()->find($value)?->name)
                ->visible(fn (Get $get): bool => self::assigneeType($get) === AssigneeType::User)
                ->required(fn (Get $get): bool => self::assigneeType($get) === AssigneeType::User)
                ->rule(fn (Get $get) => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                    if (! self::userQuery($get('tenant_id'))->whereKey($value)->exists()) {
                        $fail('این شخص عضو این '.config('sarasystem.tenant_label').' نیست.');
                    }
                }),
            CheckboxList::make('actions')
                ->label('کارهای مجاز در این مرحله')
                ->helperText('«تأیید»، «برداشتن کار» و «یادداشت» همیشه مجازند.')
                ->options(StepAction::class)
                ->default([StepAction::Reject->value, StepAction::Return->value])
                ->columns(3)
                ->columnSpanFull(),
            // Settings modules add to a step, e.g. its documents (ADR 0019).
            ...collect(WorkflowStepExtensions::all())
                ->map(fn (StepExtension $extension): Group => Group::make($extension->designerFields())
                    ->statePath('extensions.'.$extension->key())
                    ->columns(2)
                    ->columnSpanFull())
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function subjectOptions(): array
    {
        $tenant = Filament::getTenant();

        return collect(WorkflowSubjects::all())
            ->filter(fn ($subject): bool => $tenant instanceof Tenant && $subject->availableIn($tenant))
            ->map->label()
            ->all();
    }

    /**
     * Roles that can be held in the tenant, except super admin (it holds
     * every tenant anyway).
     *
     * @return array<int, string>
     */
    private static function roleOptions(mixed $tenantId): array
    {
        $tenant = filled($tenantId) ? Tenant::query()->find($tenantId) : null;

        if ($tenant === null) {
            return [];
        }

        return Role::query()->assignableIn($tenant)
            ->where('name', '!=', Role::SUPER_ADMIN)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Role $role): array => [$role->getKey() => $role->display_name])
            ->all();
    }

    /**
     * @return Builder<User>
     */
    private static function userQuery(mixed $tenantId): Builder
    {
        $tenant = filled($tenantId) ? Tenant::query()->find($tenantId) : null;

        return $tenant === null ? User::query()->whereRaw('1 = 0') : User::query()->inTenant($tenant);
    }

    private static function assigneeType(Get $get): ?AssigneeType
    {
        $type = $get('assignee_type');

        return $type instanceof AssigneeType ? $type : AssigneeType::tryFrom((string) $type);
    }
}
