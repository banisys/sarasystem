<?php

namespace App\Features;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
use Nwidart\Modules\Facades\Module;
use Nwidart\Modules\Module as ModuleInstance;
use Symfony\Component\Process\PhpExecutableFinder;

/**
 * Lists the modules and switches them on and off, so an installation can offer
 * only the features a client has (ADR 0041, ADR 0042).
 *
 * The status lives in modules_statuses.json (nwidart). A module that another
 * enabled module lists in its `requires` can't be switched off, and one whose
 * `requires` are off can't be switched on. Switching a module on prepares it in
 * fresh processes, because the running one booted without it: its migrations,
 * the permissions of its screens and the default roles' share of them.
 */
class ModuleManager
{
    /**
     * Every module with what the manager page lists, ordered by name.
     *
     * @return Collection<string, array{name: string, description: string, enabled: bool, requires: list<string>, required_by: list<string>}>
     */
    public function all(): Collection
    {
        return collect(Module::all())
            ->map(fn (ModuleInstance $module): array => [
                'name' => $module->getName(),
                'description' => $module->getDescription(),
                'enabled' => $module->isEnabled(),
                'requires' => $this->requirementsOf($module),
                'required_by' => $this->enabledModulesRequiring($module->getName()),
            ])
            ->sortBy('name')
            ->keyBy('name');
    }

    /**
     * @throws ModuleChangeRefused
     */
    public function setEnabled(string $name, bool $enabled): void
    {
        $module = Module::find($name) ?? throw new ModuleChangeRefused("ماژول {$name} وجود ندارد.");

        if ($module->isEnabled() === $enabled) {
            return;
        }

        $enabled ? $this->enable($module) : $this->disable($module);
    }

    /**
     * @throws ModuleChangeRefused
     */
    private function disable(ModuleInstance $module): void
    {
        $dependents = $this->enabledModulesRequiring($module->getName());

        if ($dependents !== []) {
            throw new ModuleChangeRefused(
                "ماژول {$module->getName()} را نمی‌توان غیرفعال کرد؛ این ماژول‌های فعال به آن نیاز دارند: ".implode('، ', $dependents).'.'
            );
        }

        $module->disable();
        $this->forgetCaches();
    }

    /**
     * @throws ModuleChangeRefused
     */
    private function enable(ModuleInstance $module): void
    {
        $missing = array_values(array_filter(
            $this->requirementsOf($module),
            fn (string $name): bool => ! (Module::find($name)?->isEnabled() ?? false),
        ));

        if ($missing !== []) {
            throw new ModuleChangeRefused(
                "ماژول {$module->getName()} را نمی‌توان فعال کرد؛ ابتدا این ماژول‌ها را فعال کنید: ".implode('، ', $missing).'.'
            );
        }

        $module->enable();

        try {
            $this->prepare($module->getName());
        } catch (ModuleChangeRefused $exception) {
            $module->disable();

            throw $exception;
        } finally {
            $this->forgetCaches();
        }
    }

    /**
     * @throws ModuleChangeRefused when a step fails
     */
    private function prepare(string $name): void
    {
        $steps = [
            ['module:migrate', $name, '--force'],
            ['shield:generate', '--all', '--panel=admin', '--ignore-existing-policies'],
            ['roles:install-defaults', '--add-missing-permissions'],
        ];

        foreach ($steps as $step) {
            $result = Process::path(base_path())->timeout(300)->run([
                (new PhpExecutableFinder)->find() ?: PHP_BINARY,
                'artisan',
                ...$step,
                '--no-interaction',
            ]);

            if ($result->failed()) {
                report(new ModuleChangeRefused("{$step[0]}: ".$result->errorOutput().$result->output()));

                throw new ModuleChangeRefused("آماده‌سازی ماژول {$name} شکست خورد ({$step[0]}) و ماژول غیرفعال ماند.");
            }
        }
    }

    /**
     * The caches built while the old status list was in force (config, routes,
     * views, the module provider list and permissions).
     */
    private function forgetCaches(): void
    {
        Artisan::call('optimize:clear');
        Artisan::call('permission:cache-reset');
    }

    /**
     * @return list<string>
     */
    private function requirementsOf(ModuleInstance $module): array
    {
        return array_values((array) $module->get('requires', []));
    }

    /**
     * @return list<string>
     */
    private function enabledModulesRequiring(string $name): array
    {
        return collect(Module::allEnabled())
            ->filter(fn (ModuleInstance $other): bool => in_array($name, $this->requirementsOf($other), true))
            ->map(fn (ModuleInstance $other): string => $other->getName())
            ->sort()
            ->values()
            ->all();
    }
}
