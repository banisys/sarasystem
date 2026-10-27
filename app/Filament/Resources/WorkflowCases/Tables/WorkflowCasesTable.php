<?php

namespace App\Filament\Resources\WorkflowCases\Tables;

use App\Models\WorkflowCase;
use App\Workflow\WorkflowSubjects;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WorkflowCasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('step_started_at')
            ->emptyStateHeading('کارتابل شما خالی است')
            ->emptyStateDescription('پرونده‌هایی که به مرحله‌ی شما برسند اینجا نمایش داده می‌شوند.')
            ->columns([
                TextColumn::make('subject_id')
                    ->label('پرونده')
                    ->state(fn (WorkflowCase $record): string => $record->title()),
                TextColumn::make('subject_type')
                    ->label('نوع')
                    ->formatStateUsing(fn (string $state): string => WorkflowSubjects::find($state)?->label() ?? $state),
                TextColumn::make('workflow_name')
                    ->label('فرایند'),
                TextColumn::make('current_step')
                    ->label('مرحله')
                    ->state(fn (WorkflowCase $record): ?string => $record->currentStep()?->name),
                TextColumn::make('claimed_by_id')
                    ->label('برداشته‌شده')
                    ->state(fn (WorkflowCase $record): ?string => $record->claimed_by_id ? 'توسط شما' : null)
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('step_started_at')
                    ->label('رسیده در')
                    ->jalaliDateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('شروع فرایند')
                    ->jalaliDateTime()
                    ->sortable()
                    ->toggledHiddenByDefault(),
            ])
            ->filters([
                SelectFilter::make('subject_type')
                    ->label('نوع')
                    ->options(fn (): array => collect(WorkflowSubjects::all())->map->label()->all()),
                TernaryFilter::make('claimed')
                    ->label('برداشته‌شده توسط من')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('claimed_by_id'),
                        false: fn (Builder $query) => $query->whereNull('claimed_by_id'),
                    ),
            ])
            ->recordActions([
                ViewAction::make()->label('بررسی'),
            ]);
    }
}
