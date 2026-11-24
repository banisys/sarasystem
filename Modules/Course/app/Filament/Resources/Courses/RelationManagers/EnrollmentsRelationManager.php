<?php

namespace Modules\Course\Filament\Resources\Courses\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EnrollmentsRelationManager extends CoursePeopleRelationManager
{
    protected static string $relationship = 'enrollments';

    protected static ?string $title = 'ثبت‌نام‌ها';

    protected static ?string $modelLabel = 'ثبت‌نام';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->memberSelect(config('sarasystem.learner_label'))->hiddenOn('edit'),
            DateTimePicker::make('starts_at')->label('شروع دسترسی')->jalali()->seconds(false)
                ->helperText('خالی: از همین حالا'),
            DateTimePicker::make('expires_at')->label('پایان دسترسی')->jalali()->seconds(false)
                ->after(fn (Get $get): ?string => filled($get('starts_at')) ? 'starts_at' : null)
                ->helperText('خالی: بدون تاریخ پایان'),
            Toggle::make('is_revoked')->label('لغو دسترسی'),
        ]);
    }

    public function table(Table $table): Table
    {
        $inCourse = fn (Builder $lessons): Builder => $lessons->where('course_id', $this->course()->getKey());

        return $table->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount([
            'completions' => fn (Builder $completed): Builder => $completed->whereHas('lesson', $inCourse),
        ]))->columns([
            TextColumn::make('user.name')->label(config('sarasystem.learner_label'))->searchable(),
            TextColumn::make('starts_at')->label('شروع دسترسی')->jalaliDateTime(),
            TextColumn::make('expires_at')->label('پایان دسترسی')->jalaliDateTime(),
            TextColumn::make('completions_count')->label('درس‌های تکمیل‌شده')->sortable(),
            IconColumn::make('is_revoked')->label('لغوشده')->boolean(),
        ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make()]);
    }
}
