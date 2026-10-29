<?php

namespace Modules\School\Filament\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\School\Models\Enrollment;

/**
 * The «پرونده‌ی تحصیلی» tab of a learner's page (ADR 0031): their grade
 * and class per academic year in this tenant. Edited in «مدرسه →
 * پرونده‌های تحصیلی». Added to the Student module's learners by this
 * module's provider.
 */
class EnrollmentsRelationManager extends RelationManager
{
    public const RELATIONSHIP = 'schoolEnrollments';

    protected static string $relationship = self::RELATIONSHIP;

    protected static ?string $title = 'پرونده‌ی تحصیلی';

    protected static ?string $modelLabel = 'پرونده‌ی تحصیلی';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('viewAny', Enrollment::class) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['term', 'grade.stage', 'major', 'classroom']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('term.title')->label('سال تحصیلی'),
                TextColumn::make('grade.title')->label('پایه')->description(fn (Enrollment $record): ?string => $record->grade?->stage?->title),
                TextColumn::make('major.title')->label('رشته')->placeholder('—'),
                TextColumn::make('classroom.title')->label('کلاس')->placeholder('—'),
            ]);
    }
}
