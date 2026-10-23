<?php

namespace Modules\Site\Filament\Resources\SiteMenus\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class MenuForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('عنوان')
                    ->helperText('فقط برای شناختن منو در پنل؛ مثلاً «منوی بالای سایت».')
                    ->required()
                    ->maxLength(255),
            ])
            ->columns(1);
    }
}
