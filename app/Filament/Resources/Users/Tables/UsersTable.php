<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Resources\Users\UserResource;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('نام')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('mobile')
                    ->label('موبایل')
                    ->searchable(),
                TextColumn::make('national_code')
                    ->label('کد ملی')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('email')
                    ->label('ایمیل')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('roles.display_name')
                    ->label('نقش‌ها')
                    ->badge(),
                IconColumn::make('is_super_admin')
                    ->label('مدیر کل')
                    ->boolean()
                    ->visible(fn (): bool => (bool) auth()->user()?->is_super_admin),
                TextColumn::make('created_at')
                    ->label('ایجاد')
                    ->jalaliDateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
                static::removeFromTenantAction(),
            ]);
    }

    /**
     * Takes away every role the user holds in the current tenant, which ends
     * their membership here. The user account and other tenants are untouched.
     */
    public static function removeFromTenantAction(): Action
    {
        return Action::make('removeFromTenant')
            ->label('حذف از '.config('sarasystem.tenant_label'))
            ->icon(Heroicon::OutlinedUserMinus)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(fn (User $record): string => "همه‌ی نقش‌های {$record->name} در «".Tenant::current()->name.'» گرفته می‌شود. حساب کاربر و عضویت او در جاهای دیگر باقی می‌ماند.')
            ->visible(fn (User $record): bool => $record->isNot(auth()->user()) && UserResource::canEdit($record))
            ->action(fn (User $record) => $record->roles()->detach());
    }
}
