<?php

namespace Modules\School\Filament\Resources\Grades\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;
use Modules\Class\Models\Subject;
use Modules\School\Models\Curriculum;
use Modules\School\Models\Grade;
use Modules\School\Models\Major;

/**
 * The «برنامه‌ی درسی» tab of a grade (ADR 0031): its subjects, for every
 * major or one, with their coefficient and weekly hours.
 */
class CurriculaRelationManager extends RelationManager
{
    protected static string $relationship = 'curricula';

    protected static ?string $title = 'برنامه‌ی درسی';

    protected static ?string $modelLabel = 'درس';

    protected static ?string $pluralModelLabel = 'درس‌ها';

    public function isReadOnly(): bool
    {
        return false;
    }

    /**
     * Whoever may edit the grade sets up its curriculum.
     */
    protected function canCreate(): bool
    {
        return auth()->user()?->can('update', $this->getOwnerRecord()) ?? false;
    }

    protected function canEdit(Model $record): bool
    {
        return $this->canCreate();
    }

    protected function canDelete(Model $record): bool
    {
        return $this->canCreate();
    }

    protected function canReorder(): bool
    {
        return $this->canCreate();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('subject_id')
                    ->label('درس')
                    ->options(fn (?Curriculum $record): array => Subject::options($record?->subject_id))
                    ->searchable()
                    ->required()
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule
                        ->where('grade_id', $this->getOwnerRecord()->getKey())
                        ->where(fn ($query) => filled($get('major_id')) ? $query->where('major_id', $get('major_id')) : $query->whereNull('major_id'))),
                Select::make('major_id')
                    ->label('رشته')
                    ->helperText('خالی یعنی همه‌ی رشته‌ها.')
                    ->options(fn (): array => Major::options())
                    ->visible(fn (): bool => $this->grade()->has_majors),
                TextInput::make('coefficient')
                    ->label('ضریب')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(10)
                    ->default(1)
                    ->required(),
                TextInput::make('weekly_hours')
                    ->label('ساعت در هفته')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(40),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['subject', 'major']))
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                TextColumn::make('subject.title')->label('درس'),
                TextColumn::make('major.title')->label('رشته')->placeholder('همه')->visible(fn (): bool => $this->grade()->has_majors),
                TextColumn::make('coefficient')->label('ضریب')->formatStateUsing(fn (mixed $state): string => rtrim(rtrim((string) $state, '0'), '.')),
                TextColumn::make('weekly_hours')->label('ساعت در هفته')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('major_id')->label('رشته')->options(fn (): array => Major::options())->visible(fn (): bool => $this->grade()->has_majors),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    private function grade(): Grade
    {
        /** @var Grade */
        return $this->getOwnerRecord();
    }
}
