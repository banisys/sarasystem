<?php

namespace Modules\Student\Filament\Resources\Programs\Schemas;

use App\Models\Tenant;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Modules\Student\Models\Preregistration;

class ProgramForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('عنوان')
                    ->helperText('مثلاً «پایه هفتم»، «کارشناسی کامپیوتر»، «زبان انگلیسی - مقدماتی» یا «دوره‌ی بدو خدمت».')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->label('توضیحات')
                    ->helperText('زیر گزینه‌ی انتخاب‌شده در فرم پیش‌ثبت‌نام نمایش داده می‌شود.')
                    ->rows(3),
                Toggle::make('all_tenants')
                    ->label(fn (): string => 'همهٔ '.config('sarasystem.tenant_plural_label'))
                    ->helperText(fn (): string => 'برنامه در همهٔ '.config('sarasystem.tenant_plural_label').' فعال، از جمله مواردی که بعداً اضافه می‌شوند، ارائه می‌شود.')
                    ->default(true)
                    ->live(),
                Select::make('availableInTenants')
                    ->label(fn (): string => config('sarasystem.tenant_plural_label').' ارائه‌دهنده')
                    ->helperText(fn (): string => 'یک یا چند '.config('sarasystem.tenant_label').' انتخاب کنید.')
                    ->relationship('availableInTenants', 'name', fn (Builder $query) => $query->active()->orderBy('name'))
                    ->multiple()
                    ->searchable()
                    ->rules(['array', Rule::exists(Tenant::class, 'id')->where('is_active', true)])
                    ->visible(fn (Get $get): bool => ! $get('all_tenants'))
                    ->required(fn (Get $get): bool => ! $get('all_tenants')),
                Select::make('workflow_id')
                    ->label('فرایند پذیرش')
                    ->helperText('هر پیش‌ثبت‌نام تازه برای این برنامه از مرحله‌های این فرایند می‌گذرد؛ بدون فرایند، فقط ثبت می‌شود.')
                    ->relationship('workflow', 'name', fn (Builder $query) => $query->for((new Preregistration)->getMorphClass()))
                    ->placeholder('بدون فرایند'),
                Toggle::make('is_active')
                    ->label('نمایش در فرم پیش‌ثبت‌نام')
                    ->default(true),
            ])
            ->columns(1);
    }
}
