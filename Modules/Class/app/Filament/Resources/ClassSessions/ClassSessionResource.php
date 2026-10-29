<?php

namespace Modules\Class\Filament\Resources\ClassSessions;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Class\Filament\Resources\ClassSessions\Pages\ListClassSessions;
use Modules\Class\Filament\Resources\ClassSessions\Tables\ClassSessionsTable;
use Modules\Class\Models\ClassSession;
use UnitEnum;

/**
 * Sessions of every class of the current tenant (ADR 0023), from today on
 * by default; the same row actions as a class's «جلسه‌ها» tab.
 */
class ClassSessionResource extends Resource
{
    protected static ?string $model = ClassSession::class;

    // See TermResource.
    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'کلاس‌ها';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'class-sessions';

    protected static ?string $modelLabel = 'جلسه';

    protected static ?string $pluralModelLabel = 'جلسه‌ها';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return ClassSessionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClassSessions::route('/'),
        ];
    }
}
