<?php

namespace Modules\Contract\Workflow;

use App\Contract\ContractParties;
use App\Models\User;
use App\Models\WorkflowCase;
use App\Workflow\StepExtension;
use App\Workflow\WorkflowActionDenied;
use App\Workflow\WorkflowEngine;
use App\Workflow\WorkflowStep;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;
use Livewire\Component as Livewire;
use Modules\Contract\Filament\Actions\ContractActions;
use Modules\Contract\Models\Contract;
use Modules\Contract\Models\ContractType;
use Modules\Contract\Services\ContractException;
use Modules\Contract\Services\Contracts;

/**
 * Contracts in workflow steps (ADR 0020): a step can name contract types
 * its assignee prepares for the applicant. The step can't be approved
 * until each is prepared; the assignee does it in the cartable («تنظیم
 * قرارداد»). The drafts are issued when the applicant is accepted.
 * Setting: `extensions.contracts.prepare` (type ids).
 */
class ContractStepExtension extends StepExtension
{
    public const KEY = 'contracts';

    public function key(): string
    {
        return self::KEY;
    }

    public function designerFields(): array
    {
        return [
            Select::make('prepare')
                ->label('قراردادهایی که در این مرحله تنظیم می‌شوند')
                ->helperText('مسئول این مرحله شرایط این قراردادها را وارد می‌کند؛ تا آن موقع تأیید ممکن نیست. قرارداد پس از پذیرش صادر می‌شود و شخص آن را امضا می‌کند.')
                ->multiple()
                ->options(fn (Livewire $livewire): array => self::typeOptions($livewire)),
        ];
    }

    public function summary(WorkflowStep $step, string $subjectType): array
    {
        $titles = self::titles(self::ids($step));

        return $titles === [] ? [] : ['تنظیم قرارداد: '.implode('، ', $titles)];
    }

    public function caseActions(): array
    {
        return [$this->prepareAction(), $this->previewAction()];
    }

    public function caseDetails(): array
    {
        return [
            Section::make('قراردادها')
                ->visible(fn (WorkflowCase $record): bool => ContractParties::forType($record->subject_type) !== null && app(Contracts::class)->of($record->subject)->isNotEmpty())
                ->columns(2)
                ->schema(fn (WorkflowCase $record): array => app(Contracts::class)->of($record->subject)
                    ->map(fn (Contract $contract): TextEntry => TextEntry::make("contract_{$contract->getKey()}")
                        ->label($contract->contractType->title)
                        ->state($contract->status->getLabel())
                        ->color($contract->status->getColor())
                        ->badge())
                    ->all()),
        ];
    }

    public function beforeApprove(WorkflowCase $case, WorkflowStep $step): void
    {
        $ids = self::ids($step);

        if ($ids === []) {
            return;
        }

        $contracts = app(Contracts::class);
        $missing = self::types($ids)->filter(fn (ContractType $type): bool => $contracts->openOf($case->subject, $type) === null);

        if ($missing->isNotEmpty()) {
            throw new WorkflowActionDenied('این قراردادها هنوز تنظیم نشده‌اند: '.$missing->pluck('title')->implode('، ').'.');
        }
    }

    private function prepareAction(): Action
    {
        return Action::make('prepareContracts')
            ->label('تنظیم قرارداد')
            ->icon(Heroicon::OutlinedDocumentText)
            ->color('info')
            ->visible(fn (WorkflowCase $record): bool => self::canAct($record) && self::ids($record->currentStep()) !== [])
            ->modalWidth('3xl')
            ->modalSubmitActionLabel('ذخیره')
            ->fillForm(fn (WorkflowCase $record): array => self::types(self::ids($record->currentStep()))
                ->mapWithKeys(fn (ContractType $type): array => [self::path($type) => Contracts::state(app(Contracts::class)->openOf($record->subject, $type))])
                ->all())
            ->schema(fn (WorkflowCase $record): array => self::types(self::ids($record->currentStep()))
                ->map(function (ContractType $type) use ($record): Fieldset {
                    $contract = app(Contracts::class)->openOf($record->subject, $type);

                    // Only a draft can be changed; an issued one is shown as it is.
                    return Fieldset::make($type->title)
                        ->statePath(self::path($type))
                        ->columns(2)
                        ->schema($contract === null || $contract->isDraft()
                            ? ContractActions::termsFields($type)
                            : [Text::make('این قرارداد صادر شده است: '.$contract->status->getLabel())]);
                })
                ->all())
            ->action(function (WorkflowCase $record, array $data, Contracts $contracts, WorkflowEngine $engine): void {
                $user = self::user();

                if (! $engine->canAct($record, $user)) {
                    Notification::make()->title('این پرونده در کارتابل شما نیست.')->danger()->send();

                    return;
                }

                $saved = [];

                try {
                    foreach (self::types(self::ids($record->currentStep())) as $type) {
                        $contract = $contracts->openOf($record->subject, $type);

                        if ($contract === null || $contract->isDraft()) {
                            $contracts->saveDraft($contract, $type, $record->subject, (array) ($data[self::path($type)] ?? []), $user);
                            $saved[] = $type->title;
                        }
                    }
                } catch (ContractException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();

                    return;
                }

                if ($saved !== []) {
                    $engine->comment($record, $user, 'قرارداد تنظیم شد: '.implode('، ', $saved));
                }

                Notification::make()->title('قرارداد ذخیره شد')->success()->send();
            });
    }

    private function previewAction(): Action
    {
        return Action::make('previewContracts')
            ->label('متن قرارداد')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->visible(fn (WorkflowCase $record): bool => ContractParties::forType($record->subject_type) !== null && app(Contracts::class)->of($record->subject)->isNotEmpty())
            ->modalWidth('4xl')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('بستن')
            ->modalContent(fn (WorkflowCase $record): HtmlString => new HtmlString(app(Contracts::class)->of($record->subject)
                ->map(fn (Contract $contract): string => ContractActions::html($contract)->toHtml())
                ->implode('<hr class="my-6">')));
    }

    private static function path(ContractType $type): string
    {
        return "type_{$type->getKey()}";
    }

    /**
     * Type ids the step names.
     *
     * @return list<int>
     */
    private static function ids(?WorkflowStep $step): array
    {
        return array_values(array_map('intval', (array) ($step?->extension(self::KEY)['prepare'] ?? [])));
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, ContractType>
     */
    private static function types(array $ids): Collection
    {
        return $ids === [] ? new Collection : ContractType::definedTypes()->whereKey($ids)->ordered()->get();
    }

    /**
     * @param  list<int>  $ids
     * @return list<string>
     */
    private static function titles(array $ids): array
    {
        return self::types($ids)->pluck('title')->all();
    }

    /**
     * Active types of the party the designed workflow is for.
     *
     * @return array<int, string>
     */
    private static function typeOptions(Livewire $livewire): array
    {
        $subjectType = data_get($livewire, 'data.subject_type');
        $party = filled($subjectType) ? ContractParties::forType((string) $subjectType) : null;

        return $party === null ? [] : ContractType::definedTypes()->active()->forParty($party->key())->ordered()->pluck('title', 'id')->all();
    }

    private static function canAct(WorkflowCase $case): bool
    {
        $user = auth()->user();

        return $user instanceof User && app(WorkflowEngine::class)->canAct($case, $user);
    }

    private static function user(): User
    {
        /** @var User */
        return auth()->user();
    }
}
