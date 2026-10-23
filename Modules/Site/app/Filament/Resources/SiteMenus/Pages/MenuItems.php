<?php

namespace Modules\Site\Filament\Resources\SiteMenus\Pages;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Modules\Site\Filament\Resources\SiteMenus\MenuResource;
use Modules\Site\Filament\Resources\SiteMenus\Schemas\MenuItemForm;
use Modules\Site\Models\Menu;
use Modules\Site\Models\MenuItem;

/**
 * A menu's items as a tree (ADR 0012): add / edit / delete in modals, drag
 * to reorder or to move under another item. Needs Update:Menu to change.
 */
class MenuItems extends ViewRecord
{
    protected static string $resource = MenuResource::class;

    public function getTitle(): string|Htmlable
    {
        return "آیتم‌های منوی «{$this->getRecord()->title}»";
    }

    public function getBreadcrumb(): string
    {
        return 'آیتم‌ها';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('site::filament.menu-tree')
                ->viewData(fn (): array => [
                    'items' => $this->menu()->itemTree(),
                    'canEdit' => $this->canEditItems(),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->addItemAction(),
            EditAction::make()
                ->label('ویرایش منو')
                ->color('gray')
                ->schema(fn (Schema $schema): Schema => MenuResource::form($schema)),
        ];
    }

    /**
     * Arguments: `parent` (item id) to add under an item, none for the top level.
     */
    public function addItemAction(): Action
    {
        return Action::make('addItem')
            ->label(fn (array $arguments): string => isset($arguments['parent']) ? 'افزودن زیرمنو' : 'افزودن آیتم')
            ->icon(Heroicon::OutlinedPlus)
            ->visible(fn (): bool => $this->canEditItems())
            ->schema(fn (Schema $schema): Schema => MenuItemForm::configure($schema))
            ->action(function (array $data, array $arguments): void {
                $parent = isset($arguments['parent']) ? $this->findItem($arguments['parent']) : null;

                $this->menu()->items()->create([
                    ...MenuItemForm::prepareData($data),
                    'tenant_id' => $this->menu()->tenant_id,
                    'parent_id' => $parent?->id,
                    'sort' => ($this->menu()->items()->where('parent_id', $parent?->id)->max('sort') ?? -1) + 1,
                ]);
            });
    }

    /**
     * Arguments: `item` (id).
     */
    public function editItemAction(): Action
    {
        return Action::make('editItem')
            ->label('ویرایش آیتم')
            ->visible(fn (): bool => $this->canEditItems())
            ->record(fn (array $arguments): MenuItem => $this->itemOrFail($arguments['item'] ?? null))
            ->schema(fn (Schema $schema): Schema => MenuItemForm::configure($schema))
            ->fillForm(fn (MenuItem $record): array => $record->attributesToArray())
            ->action(fn (array $data, MenuItem $record) => $record->update(MenuItemForm::prepareData($data)));
    }

    /**
     * Arguments: `item` (id). Its submenu goes with it (foreign key cascade).
     */
    public function deleteItemAction(): Action
    {
        return Action::make('deleteItem')
            ->label('حذف آیتم')
            ->color('danger')
            ->visible(fn (): bool => $this->canEditItems())
            ->requiresConfirmation()
            ->modalHeading(fn (MenuItem $record): string => "حذف «{$record->title}»")
            ->modalDescription('زیرمنوی این آیتم هم حذف می‌شود.')
            ->record(fn (array $arguments): MenuItem => $this->itemOrFail($arguments['item'] ?? null))
            ->action(fn (MenuItem $record) => $record->delete());
    }

    /**
     * Called by the tree (SortableJS) after a drop: the item now sits in the
     * list of $parentId (null = top level), whose items are now in the
     * order of $orderedIds. Ids from another menu are ignored.
     *
     * @param  list<int|string>  $orderedIds
     */
    public function moveItem(int|string $itemId, int|string|null $parentId, array $orderedIds): void
    {
        abort_unless($this->canEditItems(), 403);

        $item = $this->findItem($itemId);
        $parent = filled($parentId) ? $this->findItem($parentId) : null;

        if ($item === null || (filled($parentId) && $parent === null)) {
            return;
        }

        // Not under itself or its own submenu.
        if ($parent && ($parent->id === $item->id || in_array($parent->id, $item->descendantIds(), true))) {
            return;
        }

        DB::transaction(function () use ($item, $parent, $orderedIds): void {
            $item->update(['parent_id' => $parent?->id]);

            foreach (array_values($orderedIds) as $sort => $id) {
                $this->menu()->items()->whereKey($id)->where('parent_id', $parent?->id)->update(['sort' => $sort]);
            }
        });
    }

    private function menu(): Menu
    {
        /** @var Menu */
        return $this->getRecord();
    }

    private function findItem(mixed $id): ?MenuItem
    {
        return filled($id) ? $this->menu()->items()->find($id) : null;
    }

    /**
     * Without a record of its own, an action would fall back to the page's
     * record (the menu itself).
     */
    private function itemOrFail(mixed $id): MenuItem
    {
        return $this->findItem($id) ?? abort(404);
    }

    private function canEditItems(): bool
    {
        return MenuResource::canEdit($this->getRecord());
    }
}
