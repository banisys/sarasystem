<?php

namespace Modules\Exam\Filament\Resources\Exams\Pages;

use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Modules\Exam\Filament\Resources\Exams\Schemas\ExamForm;
use Modules\Exam\Models\Exam;
use Modules\Exam\Services\ContextExams;
use Modules\Exam\Services\ExamPaperPreview;
use Modules\Exam\Support\ExamContext;
use Modules\Exam\Support\ExamContexts;
use Modules\Exam\Support\SessionExamSettings;

class CreateContextExam extends CreateExam
{
    protected static ?string $title = 'ساخت آزمون جلسه';

    protected static bool $canCreateAnother = false;

    #[Locked]
    public string $contextType;

    #[Locked]
    public int $contextId;

    private ?ExamContext $context = null;

    public function mount(?string $contextType = null, int|string|null $contextId = null): void
    {
        $id = filter_var($contextId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_if($contextType === null || $id === false, 404);

        $this->contextType = $contextType;
        $this->contextId = $id;

        parent::mount();

        $context = $this->examContext();

        if ($context->singleExamSessionUrl !== null && app(ContextExams::class)->query($context)->exists()) {
            $this->redirect($context->singleExamSessionUrl);
        }
    }

    protected function authorizeAccess(): void
    {
        parent::authorizeAccess();

        $this->examContext();
    }

    public function getSubheading(): string
    {
        return $this->examContext()->title;
    }

    public function form(Schema $schema): Schema
    {
        return ExamForm::configure($schema, forSession: true, formalSession: $this->examContext()->isFormalSession());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sessionExams')
                ->label('پیش‌نمایش آزمون جلسه')
                ->icon(Heroicon::OutlinedDocumentText)
                ->outlined()
                ->modalHeading('پیش‌نمایش نسخهٔ آزمون')
                ->modalWidth('6xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('بستن')
                ->modalContent(function (ExamPaperPreview $preview): View {
                    $this->authorizeAccess();
                    $context = $this->examContext();

                    return view('exam::filament.exam-paper-preview', [
                        'paper' => $preview->forDraft($this->form->getRawState(), $context->title, $context->classTitle, $context->startsAt),
                        'notice' => 'برگه از سؤال‌های گلچین‌شده و نمونه‌ای از ردیف‌های خودکار تهیه می‌شود. برداشت ردیف‌های خودکار و ترتیب سؤال‌ها در آزمون آنلاین ممکن است متفاوت باشد.',
                    ]);
                }),
        ];
    }

    protected function handleRecordCreation(array $data): Exam
    {
        $context = $this->examContext();
        app(ContextExams::class)->ensureCanCreate($context, 'data.title');
        if ($context->isFormalSession()) {
            $data = SessionExamSettings::FORMAL_DEFAULTS + $data;
        }

        $exam = new Exam($data);
        $exam->forceFill([
            'context_type' => $context->type,
            'context_id' => $context->id,
            'tenant_id' => $context->tenantId,
            'assigned_by_id' => auth()->id(),
            'starts_at' => $context->startsAt,
            'ends_at' => $context->endsAt,
        ])->save();

        return $exam;
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()->alpineClickHandler(null)->url($this->getRedirectUrl());
    }

    protected function getRedirectUrl(): string
    {
        return $this->examContext()->adminCreateReturnUrl;
    }

    private function examContext(): ExamContext
    {
        if ($this->context !== null) {
            return $this->context;
        }

        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        $context = app(ExamContexts::class)->resolve($this->contextType, $this->contextId, $actor);
        abort_unless($context !== null && $context->tenantId === Tenant::current()?->getKey(), 404);
        abort_unless($context->canManage && $context->allowsDirectCreation && $context->adminCreateReturnUrl !== null, 403);

        return $this->context = $context;
    }
}
