<?php

namespace Modules\Class\Filament\Resources\ClassSessions\Pages;

use App\Models\Tenant;
use App\Models\User;
use App\Support\EmployeeDirectory;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Modules\Class\Enums\SessionKind;
use Modules\Class\Enums\SessionStatus;
use Modules\Class\Filament\Actions\ExamScheduleActions;
use Modules\Class\Services\SessionExamSource;
use Modules\Exam\Support\SessionExamAdmin;
use Nwidart\Modules\Facades\Module;

class ViewExamSession extends ViewClassSession
{
    protected function authorizeAccess(): void
    {
        abort_unless($this->session()->kind === SessionKind::Exam, 404);

        abort_unless($this->canReadSession() || $this->canReadPaper(), 403);
    }

    public function getHeading(): Htmlable
    {
        return new HtmlString(view('filament.admin.page-heading', [
            'title' => $this->pageLabel(),
            'context' => $this->session()->title ?? $this->session()->subject?->title,
        ])->render());
    }

    protected function sessionHeading(): string
    {
        $title = $this->session()->title ?? $this->session()->subject?->title;

        return $title === null ? $this->pageLabel() : $this->pageLabel().' — '.$title;
    }

    protected function sessionPageActions(): array
    {
        $session = $this->session();
        $canReadSession = $this->canReadSession();
        $actions = $canReadSession ? [...parent::sessionPageActions(), ExamScheduleActions::edit()->color(Color::Indigo)] : [];

        if (Module::isEnabled('Exam')) {
            $admin = app(SessionExamAdmin::class);
            if ($canReadSession) {
                $actions[] = $admin->manageAction(
                    SessionExamSource::TYPE, $session->id,
                    fn (): bool => $session->status !== SessionStatus::Cancelled && Gate::allows('update', $session->classroom),
                )->color(Color::Teal);
            }

            $actions[] = $admin->paperAction(
                SessionExamSource::TYPE, $session->id, fn (): bool => $this->canReadPaper(),
                $session->classroom?->title, $session->starts_at,
            )->color(Color::Orange);
        }

        return $actions;
    }

    public function form(Schema $schema): Schema
    {
        return $this->canReadSession() ? parent::form($schema) : $schema->components([]);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->canReadSession() ? $data : [];
    }

    private function canReadPaper(): bool
    {
        $actor = auth()->user();
        $tenant = Tenant::current();
        $session = $this->session();

        if (! Module::isEnabled('Exam') || ! $actor instanceof User || $tenant?->getKey() !== $session->tenant_id
            || $session->status === SessionStatus::Cancelled) {
            return false;
        }

        return (app()->bound(EmployeeDirectory::class) && app(EmployeeDirectory::class)->employs($actor, $tenant))
            || ($this->canReadSession() && app(SessionExamSource::class)->resolve($session->id, $actor)?->canManage === true);
    }

    protected function pageLabel(): string
    {
        return 'جلسهٔ امتحانی';
    }

    protected function isExamPage(): bool
    {
        return true;
    }
}
