<?php

namespace App\Filament\Workflow;

use App\Models\WorkflowCaseAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;

/**
 * A case's history as an infolist entry; used in the cartable and on the
 * subject's own page (ADR 0016).
 */
class CaseHistory
{
    /**
     * @param  string  $path  the actions relation, from the infolist's record
     */
    public static function make(string $path = 'actions'): RepeatableEntry
    {
        return RepeatableEntry::make($path)
            ->hiddenLabel()
            ->columns(4)
            ->schema([
                TextEntry::make('type')->label('اقدام')->badge(),
                TextEntry::make('user.name')->label('توسط')->placeholder('سامانه / متقاضی'),
                TextEntry::make('step')
                    ->label('در مرحله')
                    ->state(fn (WorkflowCaseAction $record): ?string => $record->stepName())
                    ->placeholder('—'),
                TextEntry::make('created_at')->label('زمان')->jalaliDateTime(),
                TextEntry::make('comment')
                    ->label('توضیح')
                    ->columnSpanFull()
                    ->visible(fn (WorkflowCaseAction $record): bool => filled($record->comment)),
                TextEntry::make('changes')
                    ->label('تغییرات')
                    ->state(fn (WorkflowCaseAction $record): array => collect($record->changes ?? [])
                        ->map(fn (array $change): string => "{$change['label']}: «".($change['old'] ?? '—').'» ← «'.($change['new'] ?? '—').'»')
                        ->values()
                        ->all())
                    ->listWithLineBreaks()
                    ->columnSpanFull()
                    ->visible(fn (WorkflowCaseAction $record): bool => filled($record->changes)),
            ]);
    }
}
