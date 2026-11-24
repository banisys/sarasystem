<?php

namespace Modules\Course\Filament\Resources\Courses\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Course\Models\CourseTeacher;

class InstructorsRelationManager extends CoursePeopleRelationManager
{
    protected static string $relationship = 'instructors';

    protected static ?string $title = 'مدرسان';

    protected static ?string $modelLabel = 'مدرس';

    public function form(Schema $schema): Schema
    {
        return $schema->components([$this->memberSelect('مدرس')]);
    }

    protected function eligibleMembers(Builder $query): Builder
    {
        return $query->whereNotIn('users.id', CourseTeacher::query()->where('is_active', false)->select('user_id'));
    }

    public function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('user.name')->label('نام')->searchable()])
            ->headerActions([CreateAction::make()])
            ->recordActions([DeleteAction::make()]);
    }
}
