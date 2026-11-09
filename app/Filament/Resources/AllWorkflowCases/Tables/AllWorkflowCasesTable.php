<?php

namespace App\Filament\Resources\AllWorkflowCases\Tables;

use App\Filament\Resources\AllWorkflowCases\AllWorkflowCasesResource;
use App\Models\Workflow;
use App\Models\WorkflowCase;
use App\Workflow\CaseStatus;
use App\Workflow\WorkflowSubjects;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AllWorkflowCasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->emptyStateHeading('پرونده‌ای نیست')
            ->emptyStateDescription('پرونده‌هایی که فرایندی برایشان شروع شود، این‌جا نمایش داده می‌شوند.')
            ->columns([
                TextColumn::make('subject_id')
                    ->label('پرونده')
                    ->state(fn (WorkflowCase $record): string => $record->title()),
                TextColumn::make('subject_type')
                    ->label('نوع')
                    ->formatStateUsing(fn (string $state): string => WorkflowSubjects::find($state)?->label() ?? $state)
                    ->toggledHiddenByDefault(),
                TextColumn::make('workflow_name')
                    ->label('فرایند'),
                TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge(),
                TextColumn::make('current_step')
                    ->label('مرحله‌ی فعلی')
                    ->state(fn (WorkflowCase $record): ?string => $record->isInProgress() ? $record->currentStep()?->name : null)
                    ->placeholder('—'),
                TextColumn::make('assignee')
                    ->label('در انتظار')
                    ->state(fn (WorkflowCase $record): ?string => $record->assigneeName())
                    ->placeholder('—'),
                TextColumn::make('step_started_at')
                    ->label('رسیده به این مرحله')
                    ->jalaliDateTime()
                    ->sortable()
                    ->toggledHiddenByDefault(),
                TextColumn::make('created_at')
                    ->label('شروع')
                    ->jalaliDateTime()
                    ->sortable(),
                TextColumn::make('finished_at')
                    ->label('پایان')
                    ->jalaliDateTime()
                    ->sortable()
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('وضعیت')
                    ->options(CaseStatus::class),
                SelectFilter::make('workflow_id')
                    ->label('فرایند')
                    ->options(fn (): array => Workflow::query()->orderBy('name')->pluck('name', 'id')->all()),
                SelectFilter::make('subject_type')
                    ->label('نوع')
                    ->options(fn (): array => collect(WorkflowSubjects::all())->map->label()->all()),
            ])
            ->recordActions([
                ViewAction::make()->label('مشاهده'),
                Action::make('flowchart')
                    ->label('فلوچارت فرایند')
                    ->icon(Heroicon::OutlinedShare)
                    ->url(fn (WorkflowCase $record): string => AllWorkflowCasesResource::getUrl('flowchart', ['record' => $record])),
            ]);
    }
}
