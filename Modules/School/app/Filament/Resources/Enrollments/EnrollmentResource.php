<?php

namespace Modules\School\Filament\Resources\Enrollments;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\School\Filament\Resources\Enrollments\Pages\ListEnrollments;
use Modules\School\Filament\Resources\Enrollments\Schemas\EnrollmentForm;
use Modules\School\Filament\Resources\Enrollments\Tables\EnrollmentsTable;
use Modules\School\Models\Enrollment;
use UnitEnum;

/**
 * Learners' grade per academic year in the current tenant
 * («پرونده‌های تحصیلی», ADR 0031): filled in by classes with a grade,
 * edited in a modal, and made for next year by promotion.
 */
class EnrollmentResource extends Resource
{
    protected static ?string $model = Enrollment::class;

    // BelongsToTenant's scope limits the list; see StageResource.
    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolderOpen;

    protected static string|UnitEnum|null $navigationGroup = 'مدرسه';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'school-enrollments';

    protected static ?string $modelLabel = 'پرونده‌ی تحصیلی';

    protected static ?string $pluralModelLabel = 'پرونده‌های تحصیلی';

    public static function form(Schema $schema): Schema
    {
        return EnrollmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EnrollmentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEnrollments::route('/'),
        ];
    }
}
