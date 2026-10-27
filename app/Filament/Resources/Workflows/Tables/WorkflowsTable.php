<?php

namespace App\Filament\Resources\Workflows\Tables;

use App\Filament\Support\TextFilter;
use App\Workflow\CaseStatus;
use App\Workflow\WorkflowSubjects;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WorkflowsTable
{
    public const DELETE_DESCRIPTION = 'پرونده‌هایی که با این فرایند شروع شده‌اند با همان مرحله‌ها تا پایان ادامه پیدا می‌کنند.';

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount(['cases as open_cases_count' => fn (Builder $cases) => $cases->where('status', CaseStatus::InProgress)]))
            ->columns([
                TextColumn::make('name')
                    ->label('نام')
                    ->sortable(),
                TextColumn::make('subject_type')
                    ->label('برای')
                    ->formatStateUsing(fn (string $state): string => WorkflowSubjects::find($state)?->label() ?? $state),
                IconColumn::make('is_active')
                    ->label('فعال')
                    ->boolean(),
                TextColumn::make('open_cases_count')
                    ->label('پرونده‌های در جریان')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('آخرین تغییر')
                    ->jalaliDateTime()
                    ->sortable(),
            ])
            ->filters([
                TextFilter::make('name')->label('نام'),
                SelectFilter::make('subject_type')
                    ->label('برای')
                    ->options(fn (): array => collect(WorkflowSubjects::all())->map->label()->all()),
                TernaryFilter::make('is_active')->label('فعال'),
            ])
            ->recordActions([
                ViewAction::make()->label('نمایش فرایند'),
                EditAction::make(),
                DeleteAction::make()->modalDescription(self::DELETE_DESCRIPTION),
            ]);
    }
}
