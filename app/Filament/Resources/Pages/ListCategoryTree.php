<?php

namespace App\Filament\Resources\Pages;

use App\Support\CategoryTree;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\Enums\ActionStatus;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;

abstract class ListCategoryTree extends Page
{
    abstract protected function categoryTree(): CategoryTree;

    abstract protected function nodeCaption(Model $record): string;

    abstract protected function linkedItemLabel(): string;

    protected function nodeDetails(Model $record): ?string
    {
        return null;
    }

    #[Url]
    public string $search = '';

    public function getTitle(): string
    {
        return static::getResource()::getTitleCasePluralModelLabel();
    }

    public function getBreadcrumb(): ?string
    {
        return __('filament-panels::resources/pages/list-records.breadcrumb');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.category-tree')
                ->viewData(function (): array {
                    $tree = $this->categoryTree();

                    return [
                        'tree' => $tree,
                        'visibleIds' => $tree->matchingIds(trim($this->search)),
                        'nodeCaption' => $this->nodeCaption(...),
                        'nodeDetails' => $this->nodeDetails(...),
                        'canCreate' => static::getResource()::canCreate(),
                    ];
                }),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [$this->createAction()];
    }

    public function createAction(): CreateAction
    {
        return CreateAction::make()
            ->label(fn (array $arguments): string => isset($arguments['parent']) ? 'افزودن زیردسته' : 'افزودن دسته')
            ->icon(Heroicon::OutlinedPlus)
            ->schema(static::getResource()::form(...))
            ->mountUsing(function (Schema $schema, array $arguments): void {
                $schema->fill();

                if (isset($arguments['parent'])) {
                    $schema->fillPartially(['parent_id' => $this->categoryOrFail($arguments['parent'])->id], ['parent_id']);
                }
            })
            ->using($this->saveCategory(...));
    }

    public function editAction(): EditAction
    {
        return EditAction::make()
            ->record(fn (array $arguments): Model => $this->categoryOrFail($arguments['category'] ?? null))
            ->authorize(fn (Model $record): bool => static::getResource()::canEdit($record)
                || static::getResource()::canDelete($record))
            ->schema(static::getResource()::form(...))
            ->disabledForm(fn (Model $record): bool => ! static::getResource()::canEdit($record))
            ->modalSubmitAction(fn (Action $action, Model $record): Action|false => static::getResource()::canEdit($record) ? $action : false)
            ->extraModalFooterActions([$this->deleteCategoryAction()])
            ->before(fn (Model $record) => abort_unless(static::getResource()::canEdit($record), 403))
            ->using($this->saveCategory(...));
    }

    private function deleteCategoryAction(): DeleteAction
    {
        return DeleteAction::make()
            ->label('حذف دسته')
            ->cancelParentActions(fn (DeleteAction $action): bool => $action->getStatus() === ActionStatus::Success)
            ->modalDescription("برای حذف، ابتدا {$this->linkedItemLabel()}‌ها و زیردسته‌های این دسته را به جای دیگری منتقل کنید.")
            ->failureNotificationTitle("دسته‌ای که {$this->linkedItemLabel()} یا زیردسته دارد قابل حذف نیست.")
            ->using(function (Model $record): ?bool {
                try {
                    return $record->getConnection()->transaction(fn (): ?bool => $record->delete());
                } catch (QueryException $exception) {
                    if (! in_array($exception->getCode(), ['23001', '23503'], true)) {
                        throw $exception;
                    }

                    return false;
                }
            });
    }

    private function saveCategory(Action $action, array $data, ?Model $record = null): Model
    {
        $model = $this->getModel();
        $record ??= new $model;

        try {
            $record->fill($data)->save();
        } catch (ValidationException $exception) {
            $errors = [];
            foreach ($exception->errors() as $field => $messages) {
                $errors["mountedActions.{$action->getNestingIndex()}.data.{$field}"] = $messages;
            }

            throw ValidationException::withMessages($errors);
        }

        return $record;
    }

    private function categoryOrFail(mixed $value): Model
    {
        $id = filter_var($value, FILTER_VALIDATE_INT);
        abort_if($id === false, 404);

        return $this->getModel()::query()->findOrFail($id);
    }
}
