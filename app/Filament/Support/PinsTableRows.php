<?php

namespace App\Filament\Support;

use App\Models\TableRowPin;
use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

trait PinsTableRows
{
    private const string PIN_ACTION = 'toggleTableRowPin';

    /** @var array<string, true>|null */
    private ?array $pinnedRecordKeys = null;

    /** @return array<Action|ActionGroup> */
    public function getRecordActions(): array
    {
        $this->registerRowPinAction();

        return parent::getRecordActions();
    }

    /** @return array<string, Action> */
    public function getFlatActions(): array
    {
        $this->registerRowPinAction();

        return parent::getFlatActions();
    }

    public function getDefaultSort(Builder $query, string $direction): string|Builder|null
    {
        $sort = parent::getDefaultSort($query, $direction);

        if ($this->getPinnedRecordKeys() === []) {
            return $sort;
        }

        $sortedQuery = $sort instanceof Builder ? $sort : $query;
        $base = $sortedQuery->getQuery();
        $column = $sortedQuery->getModel()->getQualifiedKeyName();

        if ($this->hasPivotRecordKeys() && ($relationship = $this->getRelationship()) instanceof BelongsToMany) {
            $column = $relationship->qualifyPivotColumn(app($relationship->getPivotClass())->getKeyName());
        }

        $column = $base->getGrammar()->wrap($column);
        $matchingPin = $this->rowPinQuery()->whereRaw("record_key = cast({$column} as varchar)")->selectRaw('1')->toBase();

        $existingOrders = $base->orders ?? [];
        $existingBindings = $base->getRawBindings()['order'];
        $base->reorder()->orderByRaw('exists ('.$matchingPin->toSql().') desc', $matchingPin->getBindings());
        $base->orders = [...$base->orders, ...$existingOrders];
        $base->addBinding($existingBindings, 'order');

        return $sort;
    }

    public function getRecords(): Collection|Paginator|CursorPaginator
    {
        $records = parent::getRecords();

        if ($this->hasQuery() || ! $records instanceof Collection || ($pins = $this->getPinnedRecordKeys()) === []) {
            return $records;
        }

        return $records->sortBy(fn (Model|array $record): bool => ! isset($pins[$this->getRecordKey($record)]));
    }

    private function registerRowPinAction(): void
    {
        if (isset($this->flatActions[self::PIN_ACTION]) || ! $this->canPinRows()) {
            return;
        }

        $action = Action::make(self::PIN_ACTION)
            ->label(fn (Model|array $record): string => $this->isRowPinned($record) ? 'برداشتن سنجاق' : 'سنجاق کردن به بالای جدول')
            ->icon(fn (Model|array $record): Heroicon => $this->isRowPinned($record) ? Heroicon::OutlinedBookmarkSlash : Heroicon::OutlinedBookmark)
            ->color(fn (Model|array $record): string => $this->isRowPinned($record) ? 'primary' : 'gray')
            ->authorize(fn (Model|array|null $record): bool => $record !== null && $this->canPinRows())
            ->action(function (Model|array $record): void {
                $key = $this->getRecordKey($record);
                $pins = $this->rowPinQuery();

                if ($this->isRowPinned($record)) {
                    $pins->where('record_key', $key)->delete();
                } else {
                    $pins->firstOrCreate([
                        'tenant_id' => Tenant::current()->getKey(),
                        'user_id' => Filament::auth()->id(),
                        'table_key' => $this->rowPinTableKey(),
                        'record_key' => $key,
                    ]);
                }

                $this->pinnedRecordKeys = null;
                $this->getLivewire()->flushCachedTableRecords();
                $this->getLivewire()->resetPage();
            });

        foreach ($this->recordActions as $group) {
            if ($group instanceof ActionGroup && ! $group->isButtonGroup()) {
                $group->actions([$action, ...$group->getActions()]);
                $this->cacheAction($action->table($this));

                return;
            }
        }

        $this->pushRecordActions([$action]);
    }

    private function canPinRows(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'admin'
            && Tenant::current()?->is(Filament::getTenant())
            && Filament::auth()->check()
            && ! $this->getLivewire()->isTableReordering();
    }

    /** @param Model|array<string, mixed> $record */
    private function isRowPinned(Model|array $record): bool
    {
        return isset($this->getPinnedRecordKeys()[$this->getRecordKey($record)]);
    }

    /** @return array<string, true> */
    private function getPinnedRecordKeys(): array
    {
        if (! $this->canPinRows()) {
            return [];
        }

        return $this->pinnedRecordKeys ??= array_fill_keys($this->rowPinQuery()->pluck('record_key')->all(), true);
    }

    /** @return Builder<TableRowPin> */
    private function rowPinQuery(): Builder
    {
        return TableRowPin::query()->where('user_id', Filament::auth()->id())->where('table_key', $this->rowPinTableKey());
    }

    private function rowPinTableKey(): string
    {
        $livewire = $this->getLivewire();
        $owner = $livewire instanceof RelationManager || $livewire instanceof ManageRelatedRecords ? $livewire->getOwnerRecord() : null;

        return hash('sha256', json_encode([
            $livewire::class,
            $this->getIdentifier(),
            $owner?->getKey(),
        ], JSON_THROW_ON_ERROR));
    }
}
