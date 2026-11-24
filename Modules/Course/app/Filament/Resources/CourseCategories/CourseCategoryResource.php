<?php

namespace Modules\Course\Filament\Resources\CourseCategories;

use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Course\Filament\Resources\CourseCategories\Pages\ManageCourseCategories;
use Modules\Course\Models\CourseCategory;
use UnitEnum;

class CourseCategoryResource extends Resource
{
    protected static ?string $model = CourseCategory::class;

    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    protected static string|UnitEnum|null $navigationGroup = 'دوره‌های آموزشی';

    protected static ?string $modelLabel = 'دسته‌بندی دوره';

    protected static ?string $pluralModelLabel = 'دسته‌بندی دوره‌ها';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label('عنوان')->trim()->required()->maxLength(255)->scopedUnique(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('title')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('courses'))
            ->columns([
                TextColumn::make('title')->label('عنوان')->searchable()->sortable(),
                TextColumn::make('courses_count')->label('تعداد دوره‌ها'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->visible(fn (CourseCategory $record): bool => $record->courses_count === 0),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCourseCategories::route('/')];
    }
}
