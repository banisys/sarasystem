<?php

namespace Modules\School\Filament\Resources\Majors;

use App\Filament\Concerns\OnlyInPrimaryTenant;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Modules\School\Filament\Resources\Majors\Pages\ListMajors;
use Modules\School\Models\Major;
use UnitEnum;

/**
 * The majors («ریاضی و فیزیک»، «علوم تجربی»، …) of the grades that have
 * them, defined in the primary tenant (ADR 0031).
 */
class MajorResource extends Resource
{
    use OnlyInPrimaryTenant;

    protected static ?string $model = Major::class;

    // See StageResource.
    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsPointingOut;

    protected static string|UnitEnum|null $navigationGroup = 'مدرسه';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'school-majors';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $modelLabel = 'رشته';

    protected static ?string $pluralModelLabel = 'رشته‌ها';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')->label('عنوان')->placeholder('مثلاً: ریاضی و فیزیک، علوم تجربی، ادبیات و علوم انسانی')->required()->maxLength(255),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                TextColumn::make('title')->label('عنوان'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (Major $record): bool => ! self::isUsed($record))
                    ->modalDescription('رشته‌ای که کلاس یا پرونده‌ای با آن هست حذف نمی‌شود.'),
            ]);
    }

    private static function isUsed(Major $major): bool
    {
        return DB::table('school_classroom_grades')->where('major_id', $major->getKey())->exists()
            || DB::table('school_enrollments')->where('major_id', $major->getKey())->exists();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMajors::route('/'),
        ];
    }
}
