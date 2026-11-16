<?php

namespace Modules\Exam\Filament\Resources\ExamGroups;

use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Exam\Filament\Resources\ExamGroups\Pages\ManageExamGroups;
use Modules\Exam\Models\ExamGroup;
use UnitEnum;

class ExamGroupResource extends Resource
{
    protected static ?string $model = ExamGroup::class;

    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'آزمون';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'گروه آزمون';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $modelLabel = 'گروه آزمون';

    protected static ?string $pluralModelLabel = 'گروه‌های آزمون';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
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
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('exams'))
            ->columns([
                TextColumn::make('title')->label('عنوان')->searchable()->sortable(),
                TextColumn::make('exams_count')->label('تعداد آزمون‌ها')->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('آزمون‌های عضو این گروه باقی می‌مانند و بدون گروه می‌شوند.'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageExamGroups::route('/'),
        ];
    }
}
