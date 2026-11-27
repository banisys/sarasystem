<?php

namespace Modules\Course\Filament\Resources\Courses\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Modules\Course\Filament\Resources\Courses\CourseResource;
use Modules\Course\Filament\Resources\Courses\Pages\ManageCourseLessons;
use Modules\Course\Models\CourseLesson;

class LessonsRelationManager extends CourseRelationManager
{
    protected static string $relationship = 'lessons';

    protected static ?string $title = 'درس‌ها';

    protected static ?string $modelLabel = 'درس';

    public function table(Table $table): Table
    {
        return $table->recordTitleAttribute('title')->defaultSort('position')
            ->columns([
                TextColumn::make('title')->label('عنوان')->searchable(),
                TextColumn::make('code')->label('کد درس')->searchable(),
                TextColumn::make('section.title')->label('سرفصل'),
                TextColumn::make('position')->label('ترتیب')->sortable(),
                IconColumn::make('is_preview')->label('پیش‌نمایش')->boolean(),
            ])
            ->headerActions([
                Action::make('create')
                    ->label('ایجاد درس')
                    ->disabled(fn (): bool => ! $this->course()->sections()->exists())
                    ->url(fn (): string => CourseResource::getUrl('create-lesson', ['course' => $this->course()])),
            ])
            ->header($this->getPageClass() === ManageCourseLessons::class
                ? fn (): View => view('filament.admin.course-page-actions', ['actions' => $table->getHeaderActions()])
                : null)
            ->recordActions([
                EditAction::make()
                    ->url(fn (CourseLesson $record): string => CourseResource::getUrl('edit-lesson', [
                        'course' => $this->course(),
                        'record' => $record,
                    ])),
                DeleteAction::make(),
            ]);
    }
}
