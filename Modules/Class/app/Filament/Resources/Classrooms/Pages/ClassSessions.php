<?php

namespace Modules\Class\Filament\Resources\Classrooms\Pages;

use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Class\Filament\Resources\Classrooms\RelationManagers\SchedulesRelationManager;
use Modules\Class\Filament\Resources\Classrooms\RelationManagers\SessionsRelationManager;

class ClassSessions extends ClassroomSectionPage
{
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getRelationManagersContentComponent(),
            Section::make('برنامه‌های درسی')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Livewire::make(SchedulesRelationManager::class, $this->getRelationManagerLivewireData())
                        ->key(SchedulesRelationManager::class),
                ]),
        ]);
    }

    protected static function manager(): string
    {
        return SessionsRelationManager::class;
    }

    protected static function sectionTitle(): string
    {
        return 'تقویم برنامه ریزی آموزشی';
    }
}
