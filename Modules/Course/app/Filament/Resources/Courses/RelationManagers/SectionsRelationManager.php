<?php

namespace Modules\Course\Filament\Resources\Courses\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Course\Models\CourseSection;

class SectionsRelationManager extends CourseRelationManager
{
    protected static string $relationship = 'sections';

    protected static ?string $title = 'بخش‌ها';

    protected static ?string $modelLabel = 'بخش';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label('عنوان')->trim()->required()->maxLength(255),
            TextInput::make('position')->label('ترتیب')->integer()->minValue(0)->maxValue(1000000)->default(0)->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->recordTitleAttribute('title')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('lessons'))
            ->columns([
                TextColumn::make('title')->label('عنوان'),
                TextColumn::make('position')->label('ترتیب'),
                TextColumn::make('lessons_count')->label('تعداد درس‌ها'),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->visible(fn (CourseSection $record): bool => $record->lessons_count === 0),
            ]);
    }
}
