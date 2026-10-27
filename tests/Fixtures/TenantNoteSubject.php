<?php

namespace Tests\Fixtures;

use App\Models\User;
use App\Models\WorkflowCase;
use App\Workflow\WorkflowActionDenied;
use App\Workflow\WorkflowSubject;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Database\Eloquent\Model;

/**
 * Workflow subject over TenantNote, recording what the engine told it.
 *
 * @extends WorkflowSubject<TenantNote>
 */
class TenantNoteSubject extends WorkflowSubject
{
    /** @var list<array{event: string, step?: int|null, data?: array<string, mixed>}> */
    public array $events = [];

    public bool $failOnCompletion = false;

    public function model(): string
    {
        return TenantNote::class;
    }

    public function label(): string
    {
        return 'یادداشت';
    }

    public function title(Model $subject): string
    {
        return "یادداشت {$subject->title}";
    }

    public function details(): array
    {
        return [TextEntry::make('title')->label('عنوان')];
    }

    public function editForm(): array
    {
        return [TextInput::make('title')->label('عنوان')->required()];
    }

    public function attributeLabel(string $attribute): string
    {
        return $attribute === 'title' ? 'عنوان' : $attribute;
    }

    public function completionForm(Model $subject): array
    {
        return [TextInput::make('code')->label('کد')->required()];
    }

    public function moved(WorkflowCase $case): void
    {
        $this->events[] = ['event' => 'moved', 'step' => $case->current_step];
    }

    public function completed(WorkflowCase $case, User $actor, array $data): void
    {
        if ($this->failOnCompletion) {
            throw new WorkflowActionDenied('نمی‌شود.');
        }

        $this->events[] = ['event' => 'completed', 'data' => $data];
    }

    public function rejected(WorkflowCase $case, User $actor): void
    {
        $this->events[] = ['event' => 'rejected'];
    }
}
