<?php

namespace Modules\Exam\Filament\Resources\QuestionTags;

use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Exam\Filament\Resources\QuestionTags\Pages\ManageQuestionTags;
use Modules\Exam\Models\QuestionTag;
use UnitEnum;

class QuestionTagResource extends Resource
{
    protected static ?string $model = QuestionTag::class;

    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'آزمون';

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $modelLabel = 'برچسب سؤال';

    protected static ?string $pluralModelLabel = 'برچسب‌های سؤال';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('عنوان')
                    ->trim()
                    ->required()
                    ->maxLength(255)
                    ->scopedUnique()
                    ->validationMessages(['unique' => 'این عنوان در همین شعبه استفاده شده است.']),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('questions'))
            ->columns([
                TextColumn::make('title')
                    ->label('عنوان')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('questions_count')
                    ->label('تعداد سؤال‌ها')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('این برچسب از سؤال‌های مرتبط برداشته می‌شود و سؤال‌ها باقی می‌مانند.'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageQuestionTags::route('/'),
        ];
    }
}
