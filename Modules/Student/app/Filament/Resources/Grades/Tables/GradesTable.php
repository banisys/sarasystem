<?php

namespace Modules\Student\Filament\Resources\Grades\Tables;

use App\Filament\CustomFields\CustomizeFormAction;
use App\Filament\Support\TextFilter;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Student\Forms\PreregistrationForm;
use Modules\Student\Models\Grade;

class GradesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Each stage followed by its grades.
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['workflow', 'parent'])
                ->withCount('preregistrations')
                ->orderByRaw('coalesce((select p.sort from student_grades p where p.id = student_grades.parent_id), student_grades.sort)')
                ->orderByRaw('coalesce(student_grades.parent_id, student_grades.id)')
                ->orderByRaw('student_grades.parent_id is not null')
                ->orderBy('sort')
                ->orderBy('id'))
            ->columns([
                TextColumn::make('title')
                    ->label('عنوان')
                    ->formatStateUsing(fn (string $state, Grade $record): string => $record->isStage() ? $state : "— {$state}")
                    ->weight(fn (Grade $record): ?string => $record->isStage() ? 'bold' : null),
                TextColumn::make('parent.title')
                    ->label('مقطع')
                    ->placeholder('—'),
                IconColumn::make('is_active')
                    ->label('در فرم')
                    ->boolean(),
                TextColumn::make('workflow.name')
                    ->label('فرایند پذیرش')
                    ->placeholder(fn (Grade $record): string => $record->isStage() ? 'بدون فرایند' : '')
                    ->toggleable(),
                TextColumn::make('preregistrations_count')
                    ->label('تعداد پیش‌ثبت‌نام‌ها'),
                TextColumn::make('sort')
                    ->label('ترتیب')
                    ->toggledHiddenByDefault(),
            ])
            ->filters([
                TextFilter::make('title')->label('عنوان'),
                TernaryFilter::make('is_active')->label('در فرم'),
            ])
            ->recordActions([
                EditAction::make(),
                // The form's setup for this stage only (ADR 0017).
                CustomizeFormAction::make('customizeForm', new PreregistrationForm, perRecord: true)
                    ->authorize('update')
                    ->visible(fn (Grade $record): bool => $record->isStage()),
                DeleteAction::make()
                    ->modalDescription('پایه‌های این مقطع هم حذف می‌شوند. پیش‌ثبت‌نام‌ها و دانش‌آموزان حذف نمی‌شوند و عنوان پایه در آن‌ها می‌ماند.'),
            ]);
    }
}
