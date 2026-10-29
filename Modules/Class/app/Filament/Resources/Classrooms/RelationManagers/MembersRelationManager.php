<?php

namespace Modules\Class\Filament\Resources\Classrooms\RelationManagers;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Identifiers;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Class\Models\ClassMember;
use Modules\Class\Models\ClassRole;
use Modules\Class\Models\Classroom;
use Modules\Class\Services\ClassException;
use Modules\Class\Services\ClassMembers;

/**
 * The «اعضا» tab of a class (ADR 0022): people with their class roles.
 * Adding and changing go through ClassMembers, which keeps the class rules;
 * whoever may edit the class manages its members.
 */
class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    protected static ?string $title = 'اعضا';

    protected static ?string $modelLabel = 'عضو';

    protected static ?string $pluralModelLabel = 'اعضا';

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
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'classRole']))
            ->defaultSort('id')
            ->columns([
                TextColumn::make('user.name')
                    ->label('نام'),
                TextColumn::make('user.national_code')
                    ->label('کد ملی')
                    ->copyable(),
                TextColumn::make('user.mobile')
                    ->label('موبایل')
                    ->placeholder('—')
                    ->copyable(),
                TextColumn::make('classRole.title')
                    ->label('نقش در کلاس')
                    ->badge()
                    ->color(fn (ClassMember $record): string => $record->classRole->is_attendee ? 'gray' : 'info'),
                TextColumn::make('joined_on')
                    ->label('ورود')
                    ->jalaliDate()
                    ->placeholder('—'),
                TextColumn::make('left_on')
                    ->label('خروج')
                    ->jalaliDate()
                    ->placeholder('—')
                    ->color(fn (ClassMember $record): ?string => $record->hasLeft() ? 'danger' : null),
            ])
            ->filters([
                SelectFilter::make('class_role_id')
                    ->label('نقش در کلاس')
                    ->options(fn (): array => ClassRole::options()),
                TernaryFilter::make('current')
                    ->label('عضو فعلی')
                    ->default(true)
                    ->queries(
                        true: fn (Builder $query) => $query->current(),
                        false: fn (Builder $query) => $query->whereDate('left_on', '<', today()),
                    ),
            ])
            ->headerActions([$this->addAction()])
            ->recordActions([
                $this->editAction(),
                DeleteAction::make()
                    ->visible(fn (): bool => $this->canManage())
                    ->modalDescription('برای کسی که از کلاس رفته، به‌جای حذف تاریخ خروج بزنید تا در سابقه‌ی کلاس بماند.'),
            ]);
    }

    private function addAction(): Action
    {
        return Action::make('addMembers')
            ->label('افزودن عضو')
            ->icon(Heroicon::OutlinedUserPlus)
            ->visible(fn (): bool => $this->canManage())
            ->schema([
                Select::make('class_role_id')
                    ->label('نقش در کلاس')
                    ->options(fn (): array => ClassRole::options())
                    ->required()
                    ->live(),
                Select::make('user_ids')
                    ->label('افراد')
                    ->helperText('با نام، کد ملی یا موبایل جستجو کنید.')
                    ->multiple()
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search, Get $get): array => $this->searchEligible($get('class_role_id'), $search))
                    ->getOptionLabelsUsing(fn (array $values): array => User::query()->whereKey($values)->pluck('name', 'id')->all())
                    ->disabled(fn (Get $get): bool => blank($get('class_role_id')))
                    ->required(),
                DatePicker::make('joined_on')
                    ->label('تاریخ ورود')
                    ->jalali()
                    ->default(today())
                    ->required(),
            ])
            ->action(function (array $data, ClassMembers $members): void {
                $role = ClassRole::definedRoles()->findOrFail($data['class_role_id']);

                $this->attempt(
                    fn () => $members->add($this->classroom(), $role, array_map('intval', $data['user_ids']), CarbonImmutable::parse($data['joined_on'])),
                    'به کلاس اضافه شد.',
                );
            });
    }

    private function editAction(): Action
    {
        return Action::make('editMember')
            ->label('ویرایش')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->visible(fn (): bool => $this->canManage())
            ->fillForm(fn (ClassMember $record): array => [
                'class_role_id' => $record->class_role_id,
                'joined_on' => $record->joined_on?->toDateString(),
                'left_on' => $record->left_on?->toDateString(),
            ])
            ->schema([
                Select::make('class_role_id')
                    ->label('نقش در کلاس')
                    ->options(fn (): array => ClassRole::options())
                    ->required(),
                DatePicker::make('joined_on')
                    ->label('تاریخ ورود')
                    ->jalali(),
                DatePicker::make('left_on')
                    ->label('تاریخ خروج')
                    ->jalali()
                    ->afterOrEqual('joined_on'),
            ])
            ->action(function (array $data, ClassMember $record, ClassMembers $members): void {
                $role = ClassRole::definedRoles()->findOrFail($data['class_role_id']);

                $this->attempt(
                    fn () => $members->update($record, $role, self::date($data['joined_on'] ?? null), self::date($data['left_on'] ?? null)),
                    'ذخیره شد.',
                );
            });
    }

    /**
     * People of the class's tenant the role allows, matching the search.
     *
     * @return array<int, string>
     */
    private function searchEligible(mixed $roleId, string $search): array
    {
        $role = filled($roleId) ? ClassRole::definedRoles()->find($roleId) : null;
        $search = Identifiers::toLatinDigits(trim($search));

        if ($role === null || $search === '') {
            return [];
        }

        /** @var Tenant $tenant */
        $tenant = Tenant::query()->findOrFail($this->classroom()->tenant_id);

        return $role->eligibleUsers($tenant)
            ->where(fn (Builder $query) => $query
                ->whereLike('name', "%{$search}%")
                ->orWhereLike('national_code', "%{$search}%")
                ->orWhereLike('mobile', "%{$search}%"))
            ->orderBy('name')
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (User $user): array => [$user->getKey() => $user->name.($user->national_code ? ' - '.$user->national_code : '')])
            ->all();
    }

    private function canManage(): bool
    {
        return auth()->user()?->can('update', $this->classroom()) ?? false;
    }

    private function classroom(): Classroom
    {
        /** @var Classroom */
        return $this->getOwnerRecord();
    }

    private function attempt(callable $operation, string $success): void
    {
        try {
            $operation();
        } catch (ClassException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title($success)->success()->send();
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        return filled($value) ? CarbonImmutable::parse($value) : null;
    }
}
