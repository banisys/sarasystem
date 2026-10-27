<?php

namespace App\Filament\Resources\WorkflowCases\Schemas;

use App\Filament\Workflow\CaseHistory;
use App\Models\WorkflowCase;
use App\Workflow\WorkflowStepExtensions;
use App\Workflow\WorkflowSubjects;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WorkflowCaseInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('پرونده')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('workflow_name')->label('فرایند'),
                        TextEntry::make('current_step')
                            ->label('مرحله‌ی فعلی')
                            ->state(fn (WorkflowCase $record): ?string => $record->currentStep()?->name),
                        TextEntry::make('step_started_at')->label('رسیده در')->jalaliDateTime(),
                        TextEntry::make('claimedBy.name')->label('برداشته‌شده توسط')->placeholder('—'),
                        TextEntry::make('steps')
                            ->label('مرحله‌ها')
                            ->state(fn (WorkflowCase $record): string => collect($record->stepList())
                                ->map(fn ($step, int $index): string => $index === $record->current_step ? "[{$step->name}]" : $step->name)
                                ->implode(' ← '))
                            ->columnSpanFull(),
                    ]),
                // The subject's own entries, with the subject as their record.
                Section::make(fn (Section $component): string => WorkflowSubjects::find(self::case($component)->subject_type)?->label() ?? 'اطلاعات')
                    ->relationship('subject')
                    ->schema(fn (Section $component): array => WorkflowSubjects::find(self::case($component)->subject_type)?->details() ?? [])
                    ->columns(1),
                // e.g. the documents of the case (ADR 0019).
                ...collect(WorkflowStepExtensions::all())->flatMap->caseDetails()->all(),
                Section::make('تاریخچه')
                    ->collapsible()
                    ->schema([CaseHistory::make()]),
            ])
            ->columns(1);
    }

    private static function case(Section $component): WorkflowCase
    {
        /** @var WorkflowCase */
        return $component->getContainer()->getRecord();
    }
}
