<?php

namespace Modules\School\Filament\Resources\Grades;

use App\Filament\Concerns\OnlyInPrimaryTenant;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\School\Filament\Resources\Grades\Pages\ListGrades;
use Modules\School\Filament\Resources\Grades\Pages\ViewGrade;
use Modules\School\Filament\Resources\Grades\RelationManagers\CurriculaRelationManager;
use Modules\School\Models\Grade;
use Modules\School\Models\Stage;
use UnitEnum;

/**
 * The school's grades, defined in the primary tenant (ADR 0031); created
 * and edited in a modal. A grade's page has its curriculum: subjects,
 * coefficients and weekly hours.
 */
class GradeResource extends Resource
{
    use OnlyInPrimaryTenant;

    protected static ?string $model = Grade::class;

    // See StageResource.
    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|UnitEnum|null $navigationGroup = 'مدرسه';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'school-grades';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $modelLabel = 'پایه';

    protected static ?string $pluralModelLabel = 'پایه‌ها و برنامه‌ی درسی';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('stage_id')
                    ->label('دوره‌ی تحصیلی')
                    ->options(fn (): array => Stage::definedStages()->pluck('title', 'id')->all())
                    ->required(),
                TextInput::make('title')->label('عنوان')->placeholder('مثلاً: هفتم، دهم')->required()->maxLength(255),
                Toggle::make('has_majors')->label('رشته دارد')->helperText('برای پایه‌های متوسطه دوم.'),
            ])
            ->columns(1);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('پایه')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('title')->label('عنوان'),
                        TextEntry::make('stage.title')->label('دوره‌ی تحصیلی'),
                        IconEntry::make('has_majors')->label('رشته دارد')->boolean(),
                    ]),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('stage')->withCount('curricula'))
            ->defaultSort(fn (Builder $query) => $query
                ->orderBy(Stage::withoutGlobalScopes()->select('sort')->whereColumn('school_stages.id', 'school_grades.stage_id'))
                ->orderBy('sort'))
            ->reorderable('sort')
            ->columns([
                TextColumn::make('title')->label('عنوان'),
                TextColumn::make('stage.title')->label('دوره‌ی تحصیلی'),
                IconColumn::make('has_majors')->label('رشته')->boolean(),
                TextColumn::make('curricula_count')->label('درس‌ها'),
            ])
            ->filters([
                SelectFilter::make('stage_id')
                    ->label('دوره‌ی تحصیلی')
                    ->options(fn (): array => Stage::definedStages()->pluck('title', 'id')->all()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (Grade $record): bool => ! self::isUsed($record))
                    ->modalDescription('پایه‌ای که کلاس یا پرونده‌ای با آن هست حذف نمی‌شود.'),
            ]);
    }

    private static function isUsed(Grade $grade): bool
    {
        return DB::table('school_classroom_grades')->where('grade_id', $grade->getKey())->exists()
            || DB::table('school_enrollments')->where('grade_id', $grade->getKey())->exists();
    }

    public static function getRelations(): array
    {
        return [
            CurriculaRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGrades::route('/'),
            'view' => ViewGrade::route('/{record}'),
        ];
    }
}
