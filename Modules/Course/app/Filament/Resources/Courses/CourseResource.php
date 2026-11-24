<?php

namespace Modules\Course\Filament\Resources\Courses;

use App\Filament\Forms\Components\TreeSelect;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\Course\Filament\Resources\Courses\Pages\CreateCourse;
use Modules\Course\Filament\Resources\Courses\Pages\EditCourse;
use Modules\Course\Filament\Resources\Courses\Pages\ListCourses;
use Modules\Course\Filament\Resources\Courses\RelationManagers\EnrollmentsRelationManager;
use Modules\Course\Filament\Resources\Courses\RelationManagers\InstructorsRelationManager;
use Modules\Course\Filament\Resources\Courses\RelationManagers\LessonsRelationManager;
use Modules\Course\Filament\Resources\Courses\RelationManagers\SectionsRelationManager;
use Modules\Course\Models\Course;
use Modules\Course\Models\CourseCategory;
use Modules\Course\Support\CourseCategoryTree;
use UnitEnum;

class CourseResource extends Resource
{
    protected static ?string $model = Course::class;

    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|UnitEnum|null $navigationGroup = 'دوره‌های آموزشی';

    protected static ?string $modelLabel = 'دورهٔ آموزشی';

    protected static ?string $pluralModelLabel = 'دوره‌ها';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label('عنوان')->trim()->required()->maxLength(255),
            TreeSelect::make('course_category_id')->label('دسته‌بندی')
                ->nodes(fn (): array => CourseCategoryTree::current()->nodes())
                ->scopedExists(CourseCategory::class, 'id'),
            Textarea::make('description')->label('معرفی دوره')->maxLength(10000)->columnSpanFull(),
            Toggle::make('is_published')->label('منتشرشده'),
            Toggle::make('allows_self_enrollment')->label('ثبت‌نام رایگان')
                ->helperText('با فعال کردن این گزینه، اعضای واردشده می‌توانند در دورهٔ منتشرشده ثبت‌نام کنند.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('title')->label('عنوان')->searchable()->sortable(),
                TextColumn::make('category.title')->label('دسته‌بندی'),
                IconColumn::make('is_published')->label('منتشرشده')->boolean(),
                IconColumn::make('allows_self_enrollment')->label('ثبت‌نام رایگان')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_published')->label('انتشار'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [
            SectionsRelationManager::class,
            LessonsRelationManager::class,
            InstructorsRelationManager::class,
            EnrollmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCourses::route('/'),
            'create' => CreateCourse::route('/create'),
            'edit' => EditCourse::route('/{record}/edit'),
        ];
    }
}
