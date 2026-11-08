<?php

namespace App\Filament\Resources\Users\Tables;

use App\Auth\Impersonation;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Support\TextFilter;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withTenantsCount()->with('tenants:id,name'))
            ->columns([
                TextColumn::make('name')
                    ->label('نام')
                    ->sortable(),
                TextColumn::make('mobile')
                    ->label('موبایل'),
                TextColumn::make('national_code')
                    ->label('کد ملی')
                    ->toggleable(),
                TextColumn::make('email')
                    ->label('ایمیل')
                    ->toggleable(),
                TextColumn::make('roles.display_name')
                    ->label('نقش‌ها')
                    ->badge(),
                TextColumn::make('tenants_count')
                    ->label('تعداد '.config('sarasystem.tenant_plural_label'))
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->tooltip(fn (User $record): string => $record->tenants->sortBy('name')->pluck('name')->join('، ')),
                TextColumn::make('created_at')
                    ->label('ایجاد')
                    ->jalaliDateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TextFilter::make('name')->label('نام'),
                TextFilter::make('mobile')->label('موبایل'),
                TextFilter::make('national_code')->label('کد ملی'),
                TextFilter::make('email')->label('ایمیل'),
            ])
            ->recordActions([
                EditAction::make(),
                static::impersonateAction(),
                static::removeFromTenantAction(),
                // Deleting is done here, not on the edit page (ADR 0006).
                DeleteAction::make()
                    ->label('حذف حساب')
                    ->modalDescription('حساب کاربری از سامانه و از همه‌ی '.config('sarasystem.tenant_plural_label').' حذف می‌شود. برای برداشتن فقط از این '.config('sarasystem.tenant_label').' «حذف از '.config('sarasystem.tenant_label').'» را بزنید.')
                    ->visible(fn (User $record): bool => $record->isNot(auth()->user()) && UserResource::canEdit($record)),
            ]);
    }

    /**
     * Logs a super admin in as the user (ADR 0008); «بازگشت به حساب خودم»
     * brings them back to this list.
     */
    public static function impersonateAction(): Action
    {
        return Action::make('impersonate')
            ->label('ورود با این کاربر')
            ->icon(Heroicon::OutlinedArrowRightEndOnRectangle)
            ->requiresConfirmation()
            ->modalDescription('تا وقتی «بازگشت به حساب خودم» را نزنید، همه‌ی کارها به نام این کاربر انجام می‌شود.')
            ->visible(fn (User $record): bool => Impersonation::canImpersonate(auth()->user(), $record))
            ->action(function (User $record, Component $livewire): void {
                Impersonation::start(auth()->user(), $record, UserResource::getUrl('index'));

                // The panel root picks a tenant the user can open; the
                // current tenant may not be one of them.
                $panel = Filament::getPanel('admin');
                $livewire->redirect($record->canAccessPanel($panel) ? url($panel->getPath()) : route('dashboard'));
            });
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
