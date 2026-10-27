<?php

namespace App\Filament\Resources\WorkflowCases\Pages;

use App\Filament\Resources\WorkflowCases\WorkflowCaseResource;
use App\Models\User;
use App\Models\WorkflowCase;
use App\Workflow\AssigneeType;
use App\Workflow\StepAction;
use App\Workflow\WorkflowActionDenied;
use App\Workflow\WorkflowEngine;
use App\Workflow\WorkflowSubject;
use App\Workflow\WorkflowSubjects;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * One case in the cartable: its subject, its history and what the
 * assignee can do at this step (ADR 0016). Every action goes through
 * WorkflowEngine, which checks again that the case is still the user's.
 */
class ViewWorkflowCase extends ViewRecord
{
    protected static string $resource = WorkflowCaseResource::class;

    public function getTitle(): string
    {
        return $this->case()->title();
    }

    public function getSubheading(): ?string
    {
        $step = $this->case()->currentStep();

        return $step === null ? null : "مرحله‌ی «{$step->name}» — {$this->case()->workflow_name}";
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->approveAction(),
            $this->returnAction(),
            $this->rejectAction(),
            $this->editAction(),
            ActionGroup::make([
                $this->claimAction(),
                $this->releaseAction(),
                $this->commentAction(),
                ...$this->handler()?->extraActions() ?? [],
            ])->label('بیشتر')->button()->color('gray'),
        ];
    }

    private function approveAction(): Action
    {
        return Action::make('approve')
            ->label(fn (): string => $this->case()->isLastStep() ? 'تأیید نهایی' : 'تأیید و ارسال به مرحله‌ی بعد')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->modalHeading(fn (): string => $this->case()->isLastStep() ? 'تأیید نهایی' : 'تأیید و ارسال به «'.$this->case()->step($this->case()->current_step + 1)?->name.'»')
            ->modalSubmitActionLabel('تأیید')
            ->schema(fn (): array => [
                ...$this->case()->isLastStep() ? $this->handler()?->completionForm($this->subject()) ?? [] : [],
                Textarea::make('comment')->label('توضیح (اختیاری)')->rows(2),
            ])
            ->action(function (array $data): void {
                $comment = $data['comment'] ?? null;
                unset($data['comment']);

                $this->run(fn (WorkflowEngine $engine, User $user) => $engine->approve($this->case(), $user, $comment, $data), 'تأیید شد', leave: true);
            });
    }

    private function returnAction(): Action
    {
        return Action::make('return')
            ->label('برگشت')
            ->icon(Heroicon::OutlinedArrowUturnRight)
            ->color('warning')
            ->visible(fn (): bool => $this->allows(StepAction::Return) && $this->case()->current_step > 0)
            ->schema(fn (): array => [
                Select::make('step')
                    ->label('برگشت به مرحله‌ی')
                    ->options(collect($this->case()->stepList())->take($this->case()->current_step)->map->name->all())
                    ->default($this->case()->current_step - 1)
                    ->required(),
                Textarea::make('comment')->label('علت برگشت')->rows(2)->required(),
            ])
            ->action(fn (array $data) => $this->run(
                fn (WorkflowEngine $engine, User $user) => $engine->returnTo($this->case(), $user, (int) $data['step'], $data['comment']),
                'پرونده برگشت داده شد',
                leave: true,
            ));
    }

    private function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('رد')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (): bool => $this->allows(StepAction::Reject))
            ->modalHeading('رد پرونده')
            ->modalDescription('فرایند این پرونده پایان می‌یابد.')
            ->modalSubmitActionLabel('رد کن')
            ->schema([
                Textarea::make('comment')->label('علت رد')->rows(2)->required(),
            ])
            ->action(fn (array $data) => $this->run(
                fn (WorkflowEngine $engine, User $user) => $engine->reject($this->case(), $user, $data['comment']),
                'پرونده رد شد',
                leave: true,
            ));
    }

    private function editAction(): Action
    {
        return Action::make('editSubject')
            ->label('ویرایش اطلاعات')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('gray')
            ->visible(fn (): bool => $this->allows(StepAction::Edit) && ($this->handler()?->editForm() ?? []) !== [])
            ->modalWidth('4xl')
            ->schema(fn (): array => $this->handler()?->editForm() ?? [])
            ->fillForm(fn (): array => $this->handler()?->editData($this->subject()) ?? $this->subject()->attributesToArray())
            ->action(fn (array $data) => $this->run(
                fn (WorkflowEngine $engine, User $user) => $engine->edit($this->case(), $user, $data),
                'اطلاعات ذخیره شد',
            ));
    }

    private function claimAction(): Action
    {
        return Action::make('claim')
            ->label('برداشتن کار')
            ->icon(Heroicon::OutlinedHandRaised)
            ->tooltip('پرونده از کارتابل بقیه‌ی دارندگان این نقش برداشته می‌شود.')
            ->visible(fn (): bool => $this->case()->currentStep()?->assigneeType === AssigneeType::Role && $this->case()->claimed_by_id === null)
            ->action(fn () => $this->run(fn (WorkflowEngine $engine, User $user) => $engine->claim($this->case(), $user), 'پرونده برای شما برداشته شد'));
    }

    private function releaseAction(): Action
    {
        return Action::make('release')
            ->label('رها کردن کار')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->visible(fn (): bool => $this->case()->claimed_by_id !== null)
            ->action(fn () => $this->run(fn (WorkflowEngine $engine, User $user) => $engine->release($this->case(), $user), 'پرونده به کارتابل همه‌ی دارندگان نقش برگشت'));
    }

    private function commentAction(): Action
    {
        return Action::make('comment')
            ->label('افزودن یادداشت')
            ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
            ->schema([
                Textarea::make('comment')->label('یادداشت')->helperText('مثلاً نتیجه‌ی مصاحبه یا آزمون.')->rows(3)->required(),
            ])
            ->action(fn (array $data) => $this->run(
                fn (WorkflowEngine $engine, User $user) => $engine->comment($this->case(), $user, $data['comment']),
                'یادداشت ثبت شد',
            ));
    }

    /**
     * Run an engine call; afterwards go back to the cartable when the case
     * has left the user's step, otherwise reload the page.
     *
     * @param  Closure(WorkflowEngine, User): void  $call
     */
    private function run(Closure $call, string $success, bool $leave = false): void
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            $call(app(WorkflowEngine::class), $user);
        } catch (WorkflowActionDenied $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            $this->redirect(WorkflowCaseResource::getUrl('index'));

            return;
        }

        Notification::make()->title($success)->success()->send();

        $this->redirect($leave ? WorkflowCaseResource::getUrl('index') : WorkflowCaseResource::getUrl('view', ['record' => $this->case()]));
    }

    private function allows(StepAction $action): bool
    {
        return $this->case()->currentStep()?->allows($action) ?? false;
    }

    private function case(): WorkflowCase
    {
        /** @var WorkflowCase */
        return $this->getRecord();
    }

    private function subject(): Model
    {
        return $this->case()->subject;
    }

    /**
     * @return WorkflowSubject<Model>|null
     */
    private function handler(): ?WorkflowSubject
    {
        return WorkflowSubjects::find($this->case()->subject_type);
    }
}
