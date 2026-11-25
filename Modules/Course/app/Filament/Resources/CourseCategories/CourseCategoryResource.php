<?php

namespace Modules\Course\Filament\Resources\CourseCategories;

use App\Filament\Forms\Components\TreeSelect;
use App\Filament\Support\IndependentOfTenant;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Modules\Course\Filament\Resources\CourseCategories\Pages\ManageCourseCategories;
use Modules\Course\Models\CourseCategory;
use Modules\Course\Support\CourseCategoryTree;
use UnitEnum;

class CourseCategoryResource extends Resource implements IndependentOfTenant
{
    protected static ?string $model = CourseCategory::class;

    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolderOpen;

    protected static string|UnitEnum|null $navigationGroup = 'دوره‌های خودآموز';

    protected static ?string $modelLabel = 'دسته‌بندی دوره';

    protected static ?string $pluralModelLabel = 'دسته‌بندی دوره‌ها';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label('عنوان')->trim()->required()->maxLength(255)->scopedUnique()
                ->live(onBlur: true)
                ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                    if (blank($get('alias'))) {
                        $set('alias', Str::slug((string) $state, language: null));
                    }
                }),
            TextInput::make('alias')->label('نامک')->trim()->required()->maxLength(255)->scopedUnique()
                ->formatStateUsing(fn (?string $state, ?CourseCategory $record): ?string => $state
                    ?? ($record === null ? null : Str::slug($record->title, language: null))),
            TreeSelect::make('parent_id')->label('دستهٔ والد')->placeholder('بدون والد')
                ->nodes(fn (?CourseCategory $record): array => CourseCategoryTree::current()->nodes($record?->id)),
            TextInput::make('position')->label('ترتیب نمایش')->integer()->minValue(0)->maxValue(2147483647)->default(0)->required(),
            Textarea::make('short_description')->label('توضیح کوتاه')->rows(4)->maxLength(10000)->columnSpanFull(),
            Toggle::make('is_active')->label('فعال')->default(true)
                ->helperText('دستهٔ غیرفعال و زیرشاخه‌های آن در فیلتر فهرست عمومی دوره‌ها نمایش داده نمی‌شوند.'),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCourseCategories::route('/')];
    }
}
