<?php

namespace Modules\Course\Filament\Resources\Courses\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Course\Integrations\CourseExamSource;
use Modules\Course\Models\CourseLesson;
use Modules\Course\Models\CourseSection;
use Nwidart\Modules\Facades\Module;

class LessonsRelationManager extends CourseRelationManager
{
    protected static string $relationship = 'lessons';

    protected static ?string $title = 'درس‌ها';

    protected static ?string $modelLabel = 'درس';

    public function form(Schema $schema): Schema
    {
        $inCourse = fn (Builder $query): Builder => $query->where('course_id', $this->course()->getKey());

        return $schema->components([
            TextInput::make('title')->label('عنوان')->trim()->required()->maxLength(255),
            Select::make('course_section_id')->label('بخش')->required()
                ->relationship('section', 'title', modifyQueryUsing: $inCourse)
                ->scopedExists(CourseSection::class, 'id', $inCourse),
            TextInput::make('position')->label('ترتیب')->integer()->minValue(0)->maxValue(1000000)->default(0)->required(),
            TextInput::make('duration_minutes')->label('مدت (دقیقه)')->integer()->minValue(1)->maxValue(32767),
            Textarea::make('body')->label('متن درس')->maxLength(50000)->columnSpanFull(),
            SpatieMediaLibraryFileUpload::make('video')->label('ویدئو')->collection('video')
                ->disk('local')->visibility('private')->acceptedFileTypes(CourseLesson::VIDEO_TYPES)->previewable(false)->maxSize(12288),
            SpatieMediaLibraryFileUpload::make('attachments')->label('پیوست‌ها')->collection('attachments')
                ->disk('local')->visibility('private')->multiple()->preserveFilenames()->previewable(false)->maxFiles(10)->maxSize(12288),
            Toggle::make('is_preview')->label('پیش‌نمایش')->helperText('اعضای واردشده می‌توانند این درس را پیش از ثبت‌نام ببینند.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->recordTitleAttribute('title')->defaultSort('position')
            ->columns([
                TextColumn::make('title')->label('عنوان')->searchable(),
                TextColumn::make('section.title')->label('بخش'),
                TextColumn::make('position')->label('ترتیب')->sortable(),
                IconColumn::make('is_preview')->label('پیش‌نمایش')->boolean(),
            ])
            ->headerActions([
                CreateAction::make()->disabled(fn (): bool => ! $this->course()->sections()->exists())
                    ->tooltip('برای افزودن درس ابتدا یک بخش بسازید.'),
            ])
            ->recordActions([
                ...(Module::isEnabled('Exam') ? [
                    Action::make('exams')->label('آزمون‌ها')->url(CourseExamSource::managementUrl(...)),
                ] : []),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
