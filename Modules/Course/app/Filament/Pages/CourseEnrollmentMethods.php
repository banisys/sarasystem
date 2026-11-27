<?php

namespace Modules\Course\Filament\Pages;

use App\Filament\Support\IndependentOfTenant;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Modules\Course\Filament\Resources\Courses\CourseResource;
use Modules\Course\Http\Middleware\RedirectCourseToPrimaryTenant;
use Modules\Course\Models\CourseEnrollmentSettings;
use Modules\Course\Support\CoursePermissions;
use UnitEnum;

class CourseEnrollmentMethods extends Page implements IndependentOfTenant
{
    protected static string|array $routeMiddleware = [RedirectCourseToPrimaryTenant::class];

    protected static ?string $slug = 'course/enrollment';

    protected static ?string $title = 'روش‌های ثبت‌نام';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?int $navigationSort = 4;

    /** @var array<string, mixed> */
    public array $data = [];

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return CourseResource::getNavigationGroup();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && CoursePermissions::allows($user, 'Update:Course');
    }

    public function mount(): void
    {
        $this->form->fill(CourseEnrollmentSettings::current()->only([
            'manual_name', 'manual_enabled', 'free_name', 'free_enabled',
        ]));
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->livewireSubmitHandler('save')
                ->extraAttributes(['novalidate' => true])
                ->footer([Action::make('save')->label('ذخیرهٔ تغییرات')->submit('save')]),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('ثبت‌نام دستی')
                ->description('کارشناس آموزش فراگیر را از صفحهٔ دوره ثبت‌نام می‌کند.')
                ->schema([
                    TextInput::make('manual_name')->label('نام نمایشی')->trim()->required()->maxLength(255),
                    Toggle::make('manual_enabled')->label('فعال')->rules(['required', 'boolean']),
                ])->columns(2),
            Section::make('ثبت‌نام رایگان')
                ->description('فراگیر در دورهٔ منتشرشده‌ای که ثبت‌نام رایگان دارد، خودش ثبت‌نام می‌کند.')
                ->schema([
                    TextInput::make('free_name')->label('نام نمایشی')->trim()->required()->maxLength(255),
                    Toggle::make('free_enabled')->label('فعال')->rules(['required', 'boolean']),
                ])->columns(2),
        ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        CourseEnrollmentSettings::query()->updateOrCreate(
            ['id' => CourseEnrollmentSettings::SINGLETON_ID],
            $this->form->getState(),
        );

        Notification::make()->success()->title('تنظیمات ثبت‌نام ذخیره شد.')->send();
    }
}
