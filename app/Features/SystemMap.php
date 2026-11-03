<?php

namespace App\Features;

use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Nwidart\Modules\Facades\Module;

/** Builds a live installation map from manifests and registered panel screens. */
class SystemMap
{
    public function __construct(private ModuleManager $modules) {}

    /** @return array<string, mixed> */
    public function build(): array
    {
        $modules = $this->modules->all()->map(function (array $module): array {
            $metadata = (array) Module::find($module['name'])->get('sitemap', []);

            return [...$module,
                'label' => $this->label($metadata['label'] ?? $module['name']),
                'group' => $metadata['group'] ?? 'other',
                'surfaces' => $metadata['surfaces'] ?? [],
                'connections' => $metadata['connections'] ?? [],
                'screens' => [],
            ];
        })->all();

        $coreScreens = [];
        $panel = Filament::getCurrentOrDefaultPanel();

        foreach ([...$panel->getResources(), ...$panel->getPages()] as $screen) {
            $owner = str_starts_with($screen, 'Modules\\') ? explode('\\', $screen)[1] : null;

            if ($owner !== null && ! ($modules[$owner]['enabled'] ?? false)) {
                continue;
            }

            if (! $screen::shouldRegisterNavigation() || ! $screen::canAccess()) {
                continue;
            }

            if (is_subclass_of($screen, Resource::class) && ! $screen::hasPage('index')) {
                continue;
            }

            $item = ['label' => (string) $screen::getNavigationLabel(), 'url' => $screen::getUrl()];

            if ($owner === null) {
                $coreScreens[] = $item;
            } else {
                $modules[$owner]['screens'][] = $item;
            }
        }

        $connections = [];

        foreach ($modules as $module) {
            if (! $module['enabled']) {
                continue;
            }

            foreach ($module['connections'] as $connection) {
                $target = $connection['target'];

                if ($target !== 'core' && ! ($modules[$target]['enabled'] ?? false)) {
                    continue;
                }

                $connections[] = [
                    'from' => $module['label'],
                    'to' => $target === 'core' ? 'هستهٔ مشترک' : $modules[$target]['label'],
                    'label' => $this->label($connection['label']),
                ];
            }
        }

        $groupLabels = ['education' => 'آموزش و یادگیری', 'operations' => 'امور اداری', 'experience' => 'وب‌سایت و راهنما', 'other' => 'سایر امکانات'];
        $groups = array_fill_keys(array_keys($groupLabels), []);

        foreach ($modules as $module) {
            $key = array_key_exists($module['group'], $groupLabels) ? $module['group'] : 'other';
            $groups[$key][] = $module;
        }

        return [
            'groups' => array_filter($groups),
            'groupLabels' => $groupLabels,
            'modules' => $modules,
            'coreScreens' => $coreScreens,
            'connections' => $connections,
            'surfaceLabels' => ['public' => 'سایت عمومی', 'user' => 'پنل کاربری', 'admin' => 'پنل مدیریت'],
        ];
    }

    private function label(string $text): string
    {
        return strtr($text, [
            ':learners' => config('sarasystem.learner_plural_label'),
            ':learner' => config('sarasystem.learner_label'),
        ]);
    }
}
