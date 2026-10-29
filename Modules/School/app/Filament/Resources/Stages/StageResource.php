<?php

namespace Modules\School\Filament\Resources\Stages;

use App\Filament\Concerns\OnlyInPrimaryTenant;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\School\Enums\ScoreScale;
use Modules\School\Filament\Resources\Stages\Pages\ListStages;
use Modules\School\Models\Stage;
use UnitEnum;

/**
 * The school's stages («دبستان»، «متوسطه اول»، …) with how their scores
 * are given, defined in the primary tenant (ADR 0031).
 */
class StageResource extends Resource
{
    use OnlyInPrimaryTenant;

    protected static ?string $model = Stage::class;

    // BelongsToTenant's scope limits the list and new rows get the current
    // tenant; Filament's tenant scoping would need a relationship on Tenant.
    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|UnitEnum|null $navigationGroup = 'مدرسه';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'school-stages';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $modelLabel = 'دوره‌ی تحصیلی';

    protected static ?string $pluralModelLabel = 'دوره‌های تحصیلی';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')->label('عنوان')->placeholder('مثلاً: دبستان، متوسطه اول، متوسطه دوم')->required()->maxLength(255),
                Select::make('scale')->label('مقیاس نمره')->options(ScoreScale::class)->default(ScoreScale::Numeric->value)->required(),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('grades'))
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                TextColumn::make('title')->label('عنوان'),
                TextColumn::make('scale')->label('مقیاس نمره')->badge(),
                TextColumn::make('grades_count')->label('پایه‌ها'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (Stage $record): bool => $record->grades_count === 0)
                    ->modalDescription('دوره‌ای که پایه دارد حذف نمی‌شود.'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStages::route('/'),
        ];
    }
}
