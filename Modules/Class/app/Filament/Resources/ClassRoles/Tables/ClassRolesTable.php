<?php

namespace Modules\Class\Filament\Resources\ClassRoles\Tables;

use App\Models\Role;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Class\Models\ClassRole;

class ClassRolesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('members'))
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                TextColumn::make('title')
                    ->label('عنوان'),
                IconColumn::make('is_attendee')
                    ->label('شرکت‌کننده')
                    ->boolean(),
                IconColumn::make('takes_attendance')
                    ->label('حضور و غیاب می‌گیرد')
                    ->boolean(),
                IconColumn::make('records_content')
                    ->label('مطالب جلسه')
                    ->boolean(),
                TextColumn::make('allowed_role_ids')
                    ->label('فقط دارندگان')
                    ->badge()
                    ->state(fn (ClassRole $record): array => Role::query()->whereKey($record->allowed_role_ids)->get()->map->display_name->all())
                    ->placeholder('همه'),
                TextColumn::make('members_count')
                    ->label('تعداد اعضا'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (ClassRole $record): bool => $record->members_count === 0)
                    ->modalDescription('نقشی که کسی در کلاسی دارد حذف نمی‌شود.'),
            ]);
    }
}
