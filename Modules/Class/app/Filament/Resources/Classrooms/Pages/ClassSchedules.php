<?php

namespace Modules\Class\Filament\Resources\Classrooms\Pages;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Modules\Class\Filament\Resources\Classrooms\ClassroomResource;
use Modules\Class\Filament\Resources\Classrooms\RelationManagers\SchedulesRelationManager;

class ClassSchedules extends ClassroomSectionPage
{
    protected function getHeaderActions(): array
    {
        return [
            Action::make('sessions')
                ->label('جلسه‌ها')
                ->color('gray')
                ->outlined()
                ->icon(Heroicon::OutlinedClock)
                ->url(fn (): string => ClassroomResource::getUrl('sessions', ['record' => $this->getRecord()])),
        ];
    }

    protected static function manager(): string
    {
        return SchedulesRelationManager::class;
    }

    protected static function sectionTitle(): string
    {
        return 'برنامه';
    }
}
