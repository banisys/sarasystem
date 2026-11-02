<?php

namespace Modules\Tutorial\Scenarios;

use App\Models\Role;
use App\Models\WorkflowCase;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Nwidart\Modules\Facades\Module;

/**
 * A tutorial for one kind of client, e.g. a language institute (ADR 0040):
 * chapters of missions, done in order.
 */
abstract class Scenario
{
    abstract public function key(): string;

    abstract public function title(): string;

    abstract public function description(): string;

    /**
     * @return array<string, list<Mission>> chapter title => missions
     */
    abstract public function chapters(): array;

    /**
     * Modules the scenario can't do without (ADR 0041); while one is off
     * the scenario is not offered. Optional parts check enabled() instead.
     *
     * @return list<string>
     */
    public function requiredModules(): array
    {
        return [];
    }

    public function isAvailable(): bool
    {
        foreach ($this->requiredModules() as $module) {
            if (! static::enabled($module)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<Mission>
     */
    public function missions(): array
    {
        return array_merge(...array_values($this->chapters()));
    }

    /**
     * Whether «انجام خودکار همه‌ی گام‌ها» can do every mission.
     */
    public function canAutoComplete(): bool
    {
        foreach ($this->missions() as $mission) {
            if (! $mission->canPerform()) {
                return false;
            }
        }

        return true;
    }

    public function mission(string $key): ?Mission
    {
        foreach ($this->missions() as $mission) {
            if ($mission->key === $key) {
                return $mission;
            }
        }

        return null;
    }

    protected static function enabled(string $module): bool
    {
        return Module::find($module)?->isEnabled() ?? false;
    }

    /**
     * The ids (as the forms' checkboxes send them) of the global roles.
     *
     * @return list<string>
     */
    protected static function roleIds(string ...$roleNames): array
    {
        return Role::query()->whereNull('tenant_id')->whereIn('name', $roleNames)->pluck('id')->map(fn (int $id): string => (string) $id)->all();
    }

    /**
     * The record's latest workflow case.
     */
    protected static function caseOf(Model $subject): WorkflowCase
    {
        return WorkflowCase::withoutTenantScope()->where('subject_type', $subject->getMorphClass())->where('subject_id', $subject->getKey())->latest('id')->firstOrFail();
    }

    /**
     * Ten minutes from now for an hour, within today: a teacher may take
     * attendance from a little before a session starts.
     *
     * @return array{CarbonInterface, CarbonInterface}
     */
    protected static function soon(): array
    {
        $start = now()->addMinutes(10)->startOfMinute();

        return [$start, $start->copy()->addHour()->min($start->copy()->setTime(23, 59))];
    }
}
