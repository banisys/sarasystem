<?php

namespace Modules\Course\Filament\Resources\CourseTeachers;

use App\Filament\Support\IndependentOfTenant;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Course\Enums\TeacherEducation;
use Modules\Course\Filament\Resources\CourseTeachers\Pages\ManageCourseTeachers;
use Modules\Course\Http\Middleware\RedirectCourseToPrimaryTenant;
use Modules\Course\Models\CourseTeacher;
use UnitEnum;

class CourseTeacherResource extends Resource implements IndependentOfTenant
{
    protected static string|array $routeMiddleware = [RedirectCourseToPrimaryTenant::class];

    protected static ?string $model = CourseTeacher::class;

    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'دوره‌های خودآموز';

    protected static ?string $modelLabel = 'مدرس دوره';

    protected static ?string $pluralModelLabel = 'مدرسان دوره';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        $toolbar = [['bold', 'italic', 'underline'], ['bulletList', 'orderedList'], ['undo', 'redo']];

        return $schema->components([
            Select::make('user_id')->label('کاربر')->required()->searchable()
                ->relationship('user', 'name')
                ->scopedExists(User::class, 'id')
                ->scopedUnique()
                ->disabledOn('edit'),
            Select::make('education')->label('مدرک تحصیلی')->options(TeacherEducation::class)->required(),
            TextInput::make('field_of_study')->label('رشتهٔ تحصیلی')->trim()->required()->maxLength(255),
            TextInput::make('institution')->label('محل تحصیل')->trim()->required()->maxLength(255),
            TextInput::make('teaching_experience')->label('سابقهٔ تدریس')->integer()
                ->minValue(0)->maxValue(2147483647)->default(0)->required(),
            Toggle::make('is_active')->label('فعال')->default(true)
                ->helperText('مدرس غیرفعال برای انتساب تازه به دوره قابل انتخاب نیست.'),
            RichEditor::make('description')->label('توضیحات')->toolbarButtons($toolbar)->maxLength(50000)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitle(fn (CourseTeacher $record): string => $record->user->name)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('user')->withCount('assignments'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('user.name')->label('نام مدرس')->searchable()->sortable(),
                TextColumn::make('education')->label('مدرک تحصیلی'),
                TextColumn::make('field_of_study')->label('رشتهٔ تحصیلی')->searchable(),
                TextColumn::make('institution')->label('محل تحصیل')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('teaching_experience')->label('سابقهٔ تدریس')->sortable(),
                TextColumn::make('assignments_count')->label('تعداد دوره‌ها')->sortable(),
                IconColumn::make('is_active')->label('فعال')->boolean(),
                TextColumn::make('created_at')->label('تاریخ ایجاد')->jalaliDateTime()->sortable(),
            ])
            ->filters([TernaryFilter::make('is_active')->label('وضعیت فعال')])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->modalDescription('پروفایل مدرس حذف می‌شود. حساب کاربری و انتساب‌های دوره باقی می‌مانند.'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCourseTeachers::route('/')];
    }
}
