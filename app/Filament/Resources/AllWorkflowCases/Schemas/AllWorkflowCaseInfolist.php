<?php

namespace App\Filament\Resources\AllWorkflowCases\Schemas;

use App\Filament\Resources\WorkflowCases\Schemas\WorkflowCaseInfolist;
use App\Models\WorkflowCase;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AllWorkflowCaseInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('پرونده')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('status')->label('وضعیت')->badge(),
                        TextEntry::make('current_step')
                            ->label('مرحله‌ی فعلی')
                            ->state(fn (WorkflowCase $record): ?string => $record->isInProgress() ? $record->currentStep()?->name : null)
                            ->placeholder('—'),
                        TextEntry::make('assignee')
                            ->label('در انتظار')
                            ->state(fn (WorkflowCase $record): ?string => $record->assigneeName())
                            ->placeholder('—'),
                        TextEntry::make('step_started_at')
                            ->label('رسیده به این مرحله')
                            ->jalaliDateTime()
                            ->visible(fn (WorkflowCase $record): bool => $record->isInProgress()),
                        TextEntry::make('created_at')->label('شروع')->jalaliDateTime(),
                        TextEntry::make('finished_at')->label('پایان')->jalaliDateTime()->placeholder('—'),
                        TextEntry::make('steps')
                            ->label('مرحله‌ها')
                            ->state(fn (WorkflowCase $record): string => collect($record->stepList())
                                ->map(fn ($step, int $index): string => $record->isInProgress() && $index === $record->current_step ? "[{$step->name}]" : $step->name)
                                ->implode(' ← '))
                            ->columnSpanFull(),
                    ]),
                ...WorkflowCaseInfolist::details(),
            ])
            ->columns(1);
    }
}
