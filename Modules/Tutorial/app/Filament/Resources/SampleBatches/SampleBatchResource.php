<?php

namespace Modules\Tutorial\Filament\Resources\SampleBatches;

use App\Filament\Support\IndependentOfTenant;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Tutorial\Filament\Pages\StepByStepTutorial;
use Modules\Tutorial\Filament\Resources\SampleBatches\Pages\ListSampleBatches;
use Modules\Tutorial\Filament\Resources\SampleBatches\Tables\SampleBatchesTable;
use Modules\Tutorial\Models\SampleBatch;
use Modules\Tutorial\Services\ClassSampleGenerator;
use UnitEnum;

/**
 * «داده‌ی نمونه» (ADR 0046): classes full of sample data for showing a
 * client the system, each batch deleted again with one button. Super
 * admins only, while the Class module is on.
 */
class SampleBatchResource extends Resource implements IndependentOfTenant
{
    protected static ?string $model = SampleBatch::class;

    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static string|UnitEnum|null $navigationGroup = StepByStepTutorial::NAVIGATION_GROUP;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'sample-data';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $modelLabel = 'داده‌ی نمونه';

    protected static ?string $pluralModelLabel = 'داده‌ی نمونه';

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->isSuperAdmin() && ClassSampleGenerator::enabled('Class');
    }

    public static function table(Table $table): Table
    {
        return SampleBatchesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSampleBatches::route('/'),
        ];
    }
}
