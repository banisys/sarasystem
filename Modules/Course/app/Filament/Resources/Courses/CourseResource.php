<?php

namespace Modules\Course\Filament\Resources\Courses;

use App\Filament\Support\IndependentOfTenant;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Panel;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Modules\Course\Filament\Resources\Courses\Pages\CreateCourse;
use Modules\Course\Filament\Resources\Courses\Pages\CreateCourseLesson;
use Modules\Course\Filament\Resources\Courses\Pages\EditCourse;
use Modules\Course\Filament\Resources\Courses\Pages\EditCourseLesson;
use Modules\Course\Filament\Resources\Courses\Pages\ListCourses;
use Modules\Course\Filament\Resources\Courses\Pages\ManageCourseLessons;
use Modules\Course\Filament\Resources\Courses\Pages\ManageCourseParticipants;
use Modules\Course\Filament\Resources\Courses\Pages\ManageCourseSections;
use Modules\Course\Filament\Resources\Courses\RelationManagers\EnrollmentsRelationManager;
use Modules\Course\Filament\Resources\Courses\RelationManagers\InstructorsRelationManager;
use Modules\Course\Filament\Resources\Courses\RelationManagers\LessonsRelationManager;
use Modules\Course\Filament\Resources\Courses\RelationManagers\SectionsRelationManager;
use Modules\Course\Filament\Resources\Courses\Schemas\CourseForm;
use Modules\Course\Models\Course;
use UnitEnum;

class CourseResource extends Resource implements IndependentOfTenant
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
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withExists('lessons'))
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
                Action::make('lessons')
                    ->label('درس‌ها')
                    ->icon(Heroicon::OutlinedPlay)
                    ->color('warning')
                    ->authorize(fn (Course $record): bool => ManageCourseLessons::canAccess(['record' => $record]))
                    ->url(fn (Course $record): string => static::getUrl('lessons', ['record' => $record])),
                Action::make('participants')
                    ->label('شرکت‌کنندگان')
                    ->icon(Heroicon::OutlinedUsers)
                    ->color('info')
                    ->authorize(fn (Course $record): bool => ManageCourseParticipants::canAccess(['record' => $record]))
                    ->url(fn (Course $record): string => static::getUrl('participants', ['record' => $record])),
                EditAction::make(),
                DeleteAction::make()->visible(fn (Course $record): bool => ! $record->lessons_exists),
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
            'lessons' => ManageCourseLessons::route('/{record}/lessons'),
            'create-lesson' => CreateCourseLesson::route('/{course}/lessons/create'),
            'edit-lesson' => EditCourseLesson::route('/{course}/lessons/{record}/edit'),
            'legacy-activities' => new PageRegistration(
                page: ManageCourseLessons::class,
                route: fn (Panel $panel): RoutingRoute => Route::get(
                    '/{record}/activities',
                    fn (Request $request): RedirectResponse => redirect()->to(URL::query(
                        static::getUrl('lessons', ['record' => $request->route('record')]),
                        $request->query(),
                    )),
                )->middleware(ManageCourseLessons::getRouteMiddleware($panel)),
            ),
            'participants' => ManageCourseParticipants::route('/{record}/participants'),
        ];
    }
}
