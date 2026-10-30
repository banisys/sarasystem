<?php

namespace Modules\Document\Workflow;

use App\Documents\DocumentHolders;
use App\Models\User;
use App\Models\WorkflowCase;
use App\Workflow\StepExtension;
use App\Workflow\WorkflowActionDenied;
use App\Workflow\WorkflowEngine;
use App\Workflow\WorkflowStep;
use Ariaieboy\Jalali\CalendarUtils;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component as Livewire;
use Modules\Document\Enums\DocumentStatus;
use Modules\Document\Filament\Actions\DocumentActions;
use Modules\Document\Models\Document;
use Modules\Document\Models\DocumentType;
use Modules\Document\Services\Documents;

/**
 * Documents in workflow steps (ADR 0019): a step can name document types
 * its assignee must upload and types its assignee must approve. The step
 * can't be approved until that is done; the assignee does it in the
 * cartable («بارگذاری مدارک», «بررسی مدارک»), whatever the types' roles.
 * Settings: `extensions.documents.upload` / `.verify` (type ids).
 */
class DocumentStepExtension extends StepExtension
{
    public const KEY = 'documents';

    private const DECISION_APPROVE = 'approve';

    private const DECISION_REJECT = 'reject';

    public function key(): string
    {
        return self::KEY;
    }

    public function designerFields(): array
    {
        return [
            Select::make('upload')
                ->label('مدارکی که در این مرحله بارگذاری می‌شوند')
                ->helperText('مسئول این مرحله باید این مدارک را بارگذاری کند (اگر متقاضی بارگذاری کرده باشد، کافی است)؛ تا آن موقع تأیید ممکن نیست.')
                ->multiple()
                ->options(fn (Livewire $livewire): array => self::typeOptions($livewire)),
            Select::make('verify')
                ->label('مدارکی که در این مرحله تأیید می‌شوند')
                ->helperText('مسئول این مرحله این مدارک را تأیید یا رد می‌کند؛ تا همه تأیید نشوند، تأیید مرحله ممکن نیست.')
                ->multiple()
                ->options(fn (Livewire $livewire): array => self::typeOptions($livewire)),
        ];
    }

    public function summary(WorkflowStep $step, string $subjectType): array
    {
        $lines = [];

        if (($upload = self::titles(self::ids($step, 'upload'))) !== []) {
            $lines[] = 'بارگذاری مدارک: '.implode('، ', $upload);
        }

        if (($verify = self::titles(self::ids($step, 'verify'))) !== []) {
            $lines[] = 'تأیید مدارک: '.implode('، ', $verify);
        }

        return $lines;
    }

    public function caseActions(): array
    {
        return [$this->uploadAction(), $this->verifyAction()];
    }

    public function caseDetails(): array
    {
        return [
            Section::make('مدارک')
                ->visible(fn (WorkflowCase $record): bool => DocumentHolders::forType($record->subject_type) !== null)
                ->columns(3)
                ->schema(fn (WorkflowCase $record): array => self::checklistEntries($record)),
        ];
    }

    public function beforeApprove(WorkflowCase $case, WorkflowStep $step): void
    {
        $uploadIds = self::ids($step, 'upload');
        $verifyIds = self::ids($step, 'verify');

        if ($uploadIds === [] && $verifyIds === []) {
            return;
        }

        $documents = app(Documents::class)->of($case->subject);
        $missing = self::titles(array_filter($uploadIds, fn (int $id): bool => ! ($documents->get($id)?->isUploaded() ?? false)));
        $unapproved = self::titles(array_filter($verifyIds, fn (int $id): bool => ! ($documents->get($id)?->isApproved() ?? false)));

        if ($missing !== []) {
            throw new WorkflowActionDenied('این مدارک هنوز بارگذاری نشده‌اند: '.implode('، ', $missing).'.');
        }

        if ($unapproved !== []) {
            throw new WorkflowActionDenied('این مدارک هنوز تأیید نشده‌اند: '.implode('، ', $unapproved).'.');
        }
    }

    private function uploadAction(): Action
    {
        return Action::make('uploadDocuments')
            ->label('بارگذاری مدارک')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color('info')
            ->visible(fn (WorkflowCase $record): bool => self::canAct($record) && self::ids($record->currentStep(), 'upload') !== [])
            ->modalWidth('3xl')
            ->modalSubmitActionLabel('ذخیره')
            ->schema(fn (WorkflowCase $record): array => self::types(self::ids($record->currentStep(), 'upload'))
                ->map(function (DocumentType $type) use ($record): Fieldset {
                    $document = app(Documents::class)->of($record->subject)->get($type->getKey());

                    return Fieldset::make($type->title)
                        ->schema([
                            Text::make(self::statusLine($document))->columnSpanFull(),
                            // Optional when it is already uploaded: a new file replaces it.
                            ...array_map(
                                fn ($field) => $field->getName() === "{$type->getKey()}_files" ? $field->required(! ($document?->isUploaded() ?? false)) : $field,
                                DocumentActions::uploadFields($type, "{$type->getKey()}_"),
                            ),
                        ]);
                })
                ->all())
            ->action(function (WorkflowCase $record, array $data, Documents $documents, WorkflowEngine $engine): void {
                $user = self::user();

                if (! $engine->canAct($record, $user)) {
                    Notification::make()->title('این پرونده در کارتابل شما نیست.')->danger()->send();

                    return;
                }

                $uploaded = [];

                foreach (self::types(self::ids($record->currentStep(), 'upload')) as $type) {
                    $files = (array) ($data["{$type->getKey()}_files"] ?? []);

                    if ($files !== []) {
                        $documents->upload($record->subject, $type, $files, DocumentActions::expiry($data, "{$type->getKey()}_"), $user);
                        $uploaded[] = $type->title;
                    }
                }

                if ($uploaded !== []) {
                    $engine->comment($record, $user, 'مدارک بارگذاری شد: '.implode('، ', $uploaded));
                }

                Notification::make()->title('مدارک ذخیره شد')->success()->send();
            });
    }

    private function verifyAction(): Action
    {
        return Action::make('verifyDocuments')
            ->label('بررسی مدارک')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('info')
            ->visible(fn (WorkflowCase $record): bool => self::canAct($record) && self::ids($record->currentStep(), 'verify') !== [])
            ->modalWidth('3xl')
            ->modalSubmitActionLabel('ثبت بررسی')
            ->schema(fn (WorkflowCase $record): array => self::types(self::ids($record->currentStep(), 'verify'))
                ->map(function (DocumentType $type) use ($record): Fieldset {
                    $document = app(Documents::class)->of($record->subject)->get($type->getKey());

                    // A rejected one can still be approved after all.
                    if ($document === null || ! $document->hasFiles()) {
                        return Fieldset::make($type->title)->schema([Text::make(self::statusLine($document))]);
                    }

                    return Fieldset::make($type->title)
                        ->columns(2)
                        ->schema([
                            ...DocumentActions::fileEntries($document),
                            ToggleButtons::make("{$type->getKey()}_decision")
                                ->label('نتیجه')
                                ->options([self::DECISION_APPROVE => 'تأیید', self::DECISION_REJECT => 'رد'])
                                ->colors([self::DECISION_APPROVE => 'success', self::DECISION_REJECT => 'danger'])
                                ->default(match ($document->status) {
                                    DocumentStatus::Approved => self::DECISION_APPROVE,
                                    DocumentStatus::Rejected => self::DECISION_REJECT,
                                    default => null,
                                })
                                ->inline()
                                ->live(),
                            Textarea::make("{$type->getKey()}_reason")
                                ->label('علت رد')
                                ->rows(2)
                                ->default($document->rejection_reason)
                                ->visible(fn (Get $get): bool => $get("{$type->getKey()}_decision") === self::DECISION_REJECT)
                                ->required(fn (Get $get): bool => $get("{$type->getKey()}_decision") === self::DECISION_REJECT),
                        ]);
                })
                ->all())
            ->action(function (WorkflowCase $record, array $data, Documents $documents, WorkflowEngine $engine): void {
                $user = self::user();

                if (! $engine->canAct($record, $user)) {
                    Notification::make()->title('این پرونده در کارتابل شما نیست.')->danger()->send();

                    return;
                }

                $existing = $documents->of($record->subject);
                $lines = [];

                foreach (self::types(self::ids($record->currentStep(), 'verify')) as $type) {
                    $document = $existing->get($type->getKey());
                    $decision = $data["{$type->getKey()}_decision"] ?? null;

                    if ($document === null || $decision === null) {
                        continue;
                    }

                    if ($decision === self::DECISION_APPROVE && $document->status !== DocumentStatus::Approved) {
                        $documents->approve($document, $user);
                        $lines[] = "{$type->title}: تأیید";
                    } elseif ($decision === self::DECISION_REJECT && ($document->status !== DocumentStatus::Rejected || $document->rejection_reason !== $data["{$type->getKey()}_reason"])) {
                        $documents->reject($document, $user, (string) $data["{$type->getKey()}_reason"]);
                        $lines[] = "{$type->title}: رد ({$data["{$type->getKey()}_reason"]})";
                    }
                }

                if ($lines !== []) {
                    $engine->comment($record, $user, 'بررسی مدارک — '.implode('؛ ', $lines));
                }

                Notification::make()->title('بررسی مدارک ثبت شد')->success()->send();
            });
    }

    /**
     * The subject's required documents and any others it has, with status
     * and file links.
     *
     * @return list<TextEntry|Text>
     */
    private static function checklistEntries(WorkflowCase $case): array
    {
        $service = app(Documents::class);
        $documents = $service->of($case->subject);
        $rows = $service->checklist($case->subject);
        $requiredIds = $rows->map(fn (array $row): int => $row['type']->getKey())->all();

        $entries = [];

        foreach ($rows as $row) {
            $entries[] = self::documentEntry($row['type'], $row['document'], required: true);
        }

        foreach ($documents->except($requiredIds) as $document) {
            $entries[] = self::documentEntry($document->documentType, $document, required: false);
        }

        return $entries === [] ? [Text::make('مدرکی لازم نیست و مدرکی بارگذاری نشده است.')] : $entries;
    }

    private static function documentEntry(DocumentType $type, ?Document $document, bool $required): TextEntry
    {
        $links = $document?->fileLinks() ?? [];

        return TextEntry::make("document_{$type->getKey()}")
            ->label($type->title.($required ? '' : ' (اختیاری)'))
            ->state(self::statusLine($document))
            ->color(match (true) {
                $document === null, $document->isExpired(), $document->status === DocumentStatus::Rejected => 'danger',
                $document->status === DocumentStatus::Approved => 'success',
                default => 'warning',
            })
            ->helperText($links === [] ? null : count($links).' فایل؛ برای دیدن روی وضعیت بزنید')
            ->url($links[0]['url'] ?? null, shouldOpenInNewTab: true);
    }

    private static function statusLine(?Document $document): string
    {
        return match (true) {
            $document === null => 'بارگذاری نشده',
            $document->isExpired() => 'منقضی شده',
            $document->status === DocumentStatus::Rejected => "رد شده: {$document->rejection_reason}",
            default => $document->status->getLabel().($document->expires_at ? ' — معتبر تا '.vsprintf('%04d/%02d/%02d', CalendarUtils::toJalali($document->expires_at->year, $document->expires_at->month, $document->expires_at->day)) : ''),
        };
    }

    /**
     * Type ids an extension setting names.
     *
     * @return list<int>
     */
    private static function ids(?WorkflowStep $step, string $setting): array
    {
        return array_values(array_map('intval', (array) ($step?->extension(self::KEY)[$setting] ?? [])));
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, DocumentType>
     */
    private static function types(array $ids): Collection
    {
        return $ids === [] ? new Collection : DocumentType::definedTypes()->whereKey($ids)->ordered()->get();
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
     * Active types of the holder the designed workflow is for.
     *
     * @return array<int, string>
     */
    private static function typeOptions(Livewire $livewire): array
    {
        $subjectType = data_get($livewire, 'data.subject_type');
        $holder = filled($subjectType) ? DocumentHolders::forType((string) $subjectType) : null;

        return $holder === null ? [] : DocumentType::definedTypes()->active()->ordered()->pluck('title', 'id')->all();
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
