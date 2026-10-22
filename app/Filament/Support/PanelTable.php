<?php

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * The panel's table, bound in place of Filament's (AdminPanelProvider), so
 * every table behaves the same without each resource opting in:
 *
 * - Row actions sit in one dropdown in the first column. Actions a table
 *   passes ungrouped are wrapped in an ActionGroup; groups are kept as given.
 * - Clicking a row does nothing (no record URL or record action), so cell
 *   text can be selected and copied. Opening a record goes through the
 *   actions dropdown.
 */
class PanelTable extends Table
{
    /**
     * @param  array<Action | ActionGroup> | ActionGroup  $actions
     */
    public function pushRecordActions(array|ActionGroup $actions): static
    {
        $actions = Arr::wrap($actions);

        $ungrouped = array_values(array_filter($actions, fn ($action): bool => ! $action instanceof ActionGroup));

        if ($ungrouped === []) {
            return parent::pushRecordActions($actions);
        }

        return parent::pushRecordActions([
            ...array_filter($actions, fn ($action): bool => $action instanceof ActionGroup),
            ActionGroup::make($ungrouped),
        ]);
    }

    public function getRecordActionsPosition(): RecordActionsPosition
    {
        return $this->evaluate($this->recordActionsPosition)
            ?? (($this->getContentGrid() || $this->hasColumnsLayout()) ? RecordActionsPosition::AfterContent : RecordActionsPosition::BeforeColumns);
    }

    /**
     * @param  Model | array<string, mixed>  $record
     */
    public function getRecordUrl(Model|array $record): ?string
    {
        return null;
    }

    /**
     * @param  Model | array<string, mixed>  $record
     */
    public function getRecordAction(Model|array $record): ?string
    {
        return null;
    }
}
