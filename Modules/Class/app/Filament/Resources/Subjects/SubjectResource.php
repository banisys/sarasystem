<?php

namespace Modules\Class\Filament\Resources\Subjects;

use App\Filament\Concerns\OnlyInPrimaryTenant;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Class\Filament\Resources\Subjects\Pages\ListSubjects;
use Modules\Class\Filament\Resources\Subjects\Tables\SubjectsTable;
use Modules\Class\Models\Subject;
use UnitEnum;

/**
 * The organization's subjects, defined in the primary tenant (ADR 0023);
 * created and edited in a modal.
 */
class SubjectResource extends Resource
{
    use OnlyInPrimaryTenant;

    protected static ?string $model = Subject::class;

    // See TermResource.
    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'کلاس‌ها';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'class-subjects';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $modelLabel = 'درس';

    protected static ?string $pluralModelLabel = 'درس‌ها';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('عنوان')
                    ->placeholder('مثلاً: ریاضی، علوم تجربی، Speaking، ایمنی کار')
                    ->required()
                    ->maxLength(255),
                Toggle::make('is_active')
                    ->label('فعال')
                    ->default(true),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return SubjectsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubjects::route('/'),
        ];
    }
}
