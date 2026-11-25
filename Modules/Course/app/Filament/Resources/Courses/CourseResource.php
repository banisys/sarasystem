<?php

namespace Modules\Course\Filament\Resources\Courses;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
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
use Modules\Course\Filament\Resources\Courses\Pages\ManageCourseActivities;
use Modules\Course\Filament\Resources\Courses\Pages\ManageCourseSections;
use Modules\Course\Filament\Resources\Courses\RelationManagers\EnrollmentsRelationManager;
use Modules\Course\Filament\Resources\Courses\RelationManagers\InstructorsRelationManager;
use Modules\Course\Filament\Resources\Courses\RelationManagers\LessonsRelationManager;
use Modules\Course\Filament\Resources\Courses\RelationManagers\SectionsRelationManager;
use Modules\Course\Filament\Resources\Courses\Schemas\CourseForm;
use Modules\Course\Models\Course;
use UnitEnum;

class CourseResource extends Resource
{
    protected static ?string $model = Course::class;

    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|UnitEnum|null $navigationGroup = 'دوره‌های خودآموز';

    protected static ?string $navigationLabel = 'دوره‌ها';

    protected static ?string $modelLabel = 'دورهٔ خودآموز';

    protected static ?string $pluralModelLabel = 'دوره‌های خودآموز';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return CourseForm::configure($schema);
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
            ->recordActions([
                Action::make('sections')
                    ->label('سرفصل‌ها')
                    ->icon(Heroicon::OutlinedListBullet)
                    ->color('gray')
                    ->authorize(fn (Course $record): bool => ManageCourseSections::canAccess(['record' => $record]))
                    ->url(fn (Course $record): string => static::getUrl('sections', ['record' => $record])),
                Action::make('activities')
                    ->label('فعالیت‌ها')
                    ->icon(Heroicon::OutlinedPlay)
                    ->color('warning')
                    ->authorize(fn (Course $record): bool => ManageCourseActivities::canAccess(['record' => $record]))
                    ->url(fn (Course $record): string => static::getUrl('activities', ['record' => $record])),
                EditAction::make(),
            ]);
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
            'sections' => ManageCourseSections::route('/{record}/sections'),
            'activities' => ManageCourseActivities::route('/{record}/activities'),
        ];
    }
}
