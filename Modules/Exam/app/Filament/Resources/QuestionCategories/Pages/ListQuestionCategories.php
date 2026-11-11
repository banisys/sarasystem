<?php

namespace Modules\Exam\Filament\Resources\QuestionCategories\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\Enums\ActionStatus;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\QueryException;
use Livewire\Attributes\Url;
use Modules\Exam\Filament\Resources\QuestionCategories\QuestionCategoryResource;
use Modules\Exam\Models\QuestionCategory;
use Modules\Exam\Support\QuestionCategoryTree;

class ListQuestionCategories extends Page
{
    protected static string $resource = QuestionCategoryResource::class;

    #[Url]
    public string $search = '';

    public function getTitle(): string
    {
        return QuestionCategoryResource::getTitleCasePluralModelLabel();
    }

    public function getBreadcrumb(): ?string
    {
        return __('filament-panels::resources/pages/list-records.breadcrumb');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('exam::filament.question-category-tree')
                ->viewData(function (): array {
                    $tree = QuestionCategoryTree::current(withQuestionCounts: true);

                    return [
                        'tree' => $tree,
                        'visibleIds' => $tree->matchingIds(trim($this->search)),
                        'canCreate' => QuestionCategoryResource::canCreate(),
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
            ->schema(QuestionCategoryResource::form(...))
            ->fillForm(fn (array $arguments): array => isset($arguments['parent'])
                ? ['parent_id' => $this->categoryOrFail($arguments['parent'])->id]
                : [])
            ->using(QuestionCategoryResource::saveCategory(...));
    }

    public function editAction(): EditAction
    {
        return EditAction::make()
            ->record(fn (array $arguments): QuestionCategory => $this->categoryOrFail($arguments['category'] ?? null))
            ->authorize(fn (QuestionCategory $record): bool => QuestionCategoryResource::canEdit($record)
                || QuestionCategoryResource::canDelete($record))
            ->schema(QuestionCategoryResource::form(...))
            ->disabledForm(fn (QuestionCategory $record): bool => ! QuestionCategoryResource::canEdit($record))
            ->modalSubmitAction(fn (Action $action, QuestionCategory $record): Action|false => QuestionCategoryResource::canEdit($record) ? $action : false)
            ->extraModalFooterActions([$this->deleteCategoryAction()])
            ->before(fn (QuestionCategory $record) => abort_unless(QuestionCategoryResource::canEdit($record), 403))
            ->using(QuestionCategoryResource::saveCategory(...));
    }

    private function deleteCategoryAction(): DeleteAction
    {
        return DeleteAction::make()
            ->label('حذف دسته')
            ->cancelParentActions(fn (DeleteAction $action): bool => $action->getStatus() === ActionStatus::Success)
            ->modalDescription('برای حذف، ابتدا سؤال‌ها و زیردسته‌های این دسته را به جای دیگری منتقل کنید.')
            ->failureNotificationTitle('دسته‌ای که سؤال یا زیردسته دارد قابل حذف نیست.')
            ->using(function (QuestionCategory $record): ?bool {
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

    private function categoryOrFail(mixed $value): QuestionCategory
    {
        $id = filter_var($value, FILTER_VALIDATE_INT);
        abort_if($id === false, 404);

        return QuestionCategory::query()->findOrFail($id);
    }
}
