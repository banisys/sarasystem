<?php

namespace Modules\Class\Filament\Resources\ClassSessions\Pages;

use App\Models\Tenant;
use App\Models\User;
use App\Support\EmployeeDirectory;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Modules\Class\Enums\SessionKind;
use Modules\Class\Enums\SessionStatus;
use Modules\Class\Filament\Actions\ExamScheduleActions;
use Modules\Class\Filament\Actions\SessionActions;
use Modules\Class\Services\SessionExamSource;
use Modules\Class\Services\SessionScene;
use Modules\Exam\Support\SessionExamAdmin;
use Nwidart\Modules\Facades\Module;

class ViewExamSession extends ViewClassSession
{
    protected function authorizeAccess(): void
    {
        abort_unless($this->session()->kind === SessionKind::Exam, 404);

        abort_unless($this->canReadSession() || $this->canReadPaper(), 403);
    }

    public function getHeading(): string
    {
        $subject = $this->session()->subject?->title;

        return $subject === null ? $this->pageLabel() : $this->pageLabel().' — '.$subject;
    }

    public function getHeader(): View
    {
        $session = $this->session();
        $scene = app(SessionScene::class);
        $user = auth()->user();

        return view('class::filament.exam-session-header', [
            'scene' => $this->canReadSession() && $user instanceof User && $scene->canSee($user, $session) ? $scene->for($session) : null,
        ]);
    }

    protected function getHeaderActions(): array
    {
        $session = $this->session();
        $canReadSession = $this->canReadSession();
        $actions = $canReadSession ? [...parent::getHeaderActions(), ExamScheduleActions::edit()] : [];

        if (Module::isEnabled('Exam')) {
            $admin = app(SessionExamAdmin::class);
            if ($canReadSession) {
                $actions[] = $admin->manageAction(
                    SessionExamSource::TYPE, $session->id,
                    fn (): bool => $session->status !== SessionStatus::Cancelled && Gate::allows('update', $session->classroom),
                );
            }

            $actions[] = $admin->paperAction(
                SessionExamSource::TYPE, $session->id, fn (): bool => $this->canReadPaper(),
                $session->classroom?->title, $session->starts_at,
            );
        }

        return $actions;
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components($this->canReadSession() ? SessionActions::detailsEntries(exam: true) : [])->columns(1);
    }

    public function form(Schema $schema): Schema
    {
        return $this->canReadSession() ? parent::form($schema) : $schema->components([]);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->canReadSession() ? $data : [];
    }

    private function canReadSession(): bool
    {
        return static::getResource()::canView($this->session());
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
