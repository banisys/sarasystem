<?php

namespace Modules\Class\Filament\Resources\TeachingPlans;

use App\Filament\Concerns\OnlyInPrimaryTenant;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Class\Filament\Resources\TeachingPlans\Pages\ListTeachingPlans;
use Modules\Class\Filament\Resources\TeachingPlans\Schemas\TeachingPlanForm;
use Modules\Class\Filament\Resources\TeachingPlans\Tables\TeachingPlansTable;
use Modules\Class\Models\TeachingPlan;
use UnitEnum;

/**
 * The organization's teaching plans with their topics, defined in the
 * primary tenant (ADR 0025); created and edited in a modal.
 */
class TeachingPlanResource extends Resource
{
    use OnlyInPrimaryTenant;

    protected static ?string $model = TeachingPlan::class;

    // See TermResource.
    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static string|UnitEnum|null $navigationGroup = 'کلاس‌ها';

    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'teaching-plans';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $modelLabel = 'طرح درس';

    protected static ?string $pluralModelLabel = 'طرح‌های درس';

    public static function form(Schema $schema): Schema
    {
        return TeachingPlanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TeachingPlansTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTeachingPlans::route('/'),
        ];
    }
}
