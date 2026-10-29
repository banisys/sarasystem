<?php

namespace Modules\Class\Filament\Resources\Holidays\Schemas;

use App\Models\Tenant;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class HolidayForm
{
    public static function configure(Schema $schema): Schema
    {
        $tenant = Filament::getTenant();

        return $schema
            ->components([
                TextInput::make('title')
                    ->label('عنوان')
                    ->placeholder('مثلاً: تعطیلات نوروز، عید فطر')
                    ->helperText($tenant instanceof Tenant && $tenant->is_primary ? 'تعطیلی این بخش برای همه‌ی '.config('sarasystem.tenant_plural_label').' است.' : 'فقط برای همین '.config('sarasystem.tenant_label').'.')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                DatePicker::make('starts_on')
                    ->label('از')
                    ->jalali()
                    ->required()
                    ->live(),
                DatePicker::make('ends_on')
                    ->label('تا')
                    ->helperText('برای یک روز خالی بماند.')
                    ->jalali()
                    ->afterOrEqual('starts_on')
                    ->dehydrateStateUsing(fn (mixed $state, Get $get): mixed => filled($state) ? $state : $get('starts_on')),
            ])
            ->columns(2);
    }
}
