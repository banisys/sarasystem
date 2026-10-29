<?php

namespace Modules\Class\Filament\Resources\ClassRoles\Schemas;

use App\Models\Role;
use BezhanSalleh\FilamentShield\Support\Utils;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ClassRoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('عنوان')
                    ->placeholder('مثلاً: '.config('sarasystem.learner_label').'، مدرس، ناظر، پشتیبان، کمک‌مربی')
                    ->required()
                    ->maxLength(255),
                Select::make('allowed_role_ids')
                    ->label('فقط دارندگان این نقش‌ها')
                    ->helperText('نقش سیستمی در همان '.config('sarasystem.tenant_label').'؛ خالی یعنی هر عضو آن.')
                    ->multiple()
                    ->options(fn (): array => self::roleOptions())
                    ->default([])
                    ->dehydrateStateUsing(fn (?array $state): array => array_values(array_map('intval', $state ?? []))),
                Toggle::make('is_attendee')
                    ->label('شرکت‌کننده است')
                    ->helperText('حضور و غیابش ثبت می‌شود و در ظرفیت کلاس حساب می‌شود.'),
                Toggle::make('takes_attendance')
                    ->label('حضور و غیاب می‌گیرد'),
                Toggle::make('records_content')
                    ->label('مطالب جلسه را ثبت می‌کند'),
            ])
            ->columns(1);
    }

    /**
     * @return array<int, string>
     */
    private static function roleOptions(): array
    {
        return Role::query()
            ->whereNull('tenant_id')
            ->whereNotIn('name', [Role::SUPER_ADMIN, Utils::getPanelUserRoleName()])
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Role $role): array => [$role->getKey() => $role->display_name])
            ->all();
    }
}
