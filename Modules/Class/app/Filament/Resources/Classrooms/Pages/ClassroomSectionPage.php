<?php

namespace Modules\Class\Filament\Resources\Classrooms\Pages;

use Filament\Resources\Pages\ViewRecord;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Modules\Class\Filament\Resources\Classrooms\ClassroomResource;

/**
 * A class's members or calendar page, opened from the class list.
 * The section's main relation manager is rendered without tabs.
 */
abstract class ClassroomSectionPage extends ViewRecord
{
    protected static string $resource = ClassroomResource::class;

    /**
     * The whole width, for the session calendar's week and month views (ADR 0047).
     */
    protected Width|string|null $maxContentWidth = Width::Full;

    /**
     * @return class-string<RelationManager>
     */
    abstract protected static function manager(): string;

    abstract protected static function sectionTitle(): string;

    protected function getAllRelationManagers(): array
    {
        return [static::manager()];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([$this->getRelationManagersContentComponent()]);
    }

    public function getTitle(): string|Htmlable
    {
        return static::sectionTitle().' — '.$this->getRecord()->title;
    }

    public function getBreadcrumb(): string
    {
        return static::sectionTitle();
    }
}
