<?php

namespace Modules\Class\Filament\Resources\OnlineServers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Class\Enums\OnlinePlatform;
use Modules\Class\Models\OnlineServer;

/**
 * A platform's server (ADR 0073). The password is kept encrypted and left
 * as it is when the field is empty on edit.
 */
class OnlineServerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('platform')
                    ->label('بستر')
                    ->options(OnlinePlatform::serverOptions())
                    ->default(OnlinePlatform::AdobeConnect->value)
                    ->selectablePlaceholder(false)
                    ->required(),
                TextInput::make('title')
                    ->label('عنوان')
                    ->placeholder('مثلاً: سرور اول')
                    ->required()
                    ->maxLength(255),
                TextInput::make('host')
                    ->label('نشانی سرور')
                    ->placeholder('https://adobe.example.com')
                    ->url()
                    ->rule('url:http,https')
                    ->required()
                    ->maxLength(500)
                    ->columnSpanFull(),
                TextInput::make('username')
                    ->label('نام کاربری مدیر')
                    ->helperText('حسابی با دسترسی ساخت جلسه و کاربر در سرور.')
                    ->required()
                    ->maxLength(255),
                TextInput::make('password')
                    ->label('رمز مدیر')
                    ->password()
                    ->revealable()
                    ->required(fn (?OnlineServer $record): bool => $record === null)
                    ->helperText(fn (?OnlineServer $record): ?string => $record ? 'برای نگه داشتن رمز فعلی خالی بگذارید.' : null)
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->maxLength(255),
                Section::make('Adobe Connect')
                    ->schema([
                        TextInput::make('settings.folder_id')
                            ->label('شناسه‌ی پوشه‌ی جلسه‌ها (sco-id)')
                            ->helperText('اتاق کلاس‌ها در این پوشه ساخته می‌شود؛ خالی یعنی «Shared Meetings» سرور.')
                            ->numeric(),
                        TextInput::make('settings.login_prefix')
                            ->label('پیشوند نام کاربری افراد')
                            ->helperText('نام کاربری هر فرد در سرور: پیشوند + شناسه‌ی کاربر + @ + نشانی سرور؛ جدا از حساب‌هایی که سامانه‌های دیگر در همین سرور ساخته‌اند.')
                            ->default('sara')
                            ->alphaDash()
                            ->maxLength(20),
                        Toggle::make('settings.verify_ssl')
                            ->label('بررسی گواهی SSL')
                            ->helperText('فقط برای سروری با گواهی نامعتبر خاموش کنید.')
                            ->default(true),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('فعال')
                    ->helperText('کلاس‌ها فقط سرور فعال را انتخاب می‌کنند و به سرور غیرفعال وارد نمی‌شوند.')
                    ->default(true),
            ])
            ->columns(2);
    }
}
