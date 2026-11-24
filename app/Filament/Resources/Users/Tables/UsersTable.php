<?php

namespace App\Filament\Resources\Users\Tables;

use App\Auth\Impersonation;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Support\TextFilter;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => User::query())
            ->modifyQueryUsing(function (Builder $query, ListUsers $livewire): void {
                if (! static::canSearchAllBranches() || ! ($livewire->getTableFilterState('all_branches')['isActive'] ?? false)) {
                    $query->inTenant(Tenant::current());
                }
            })
            ->modifyQueryUsing(fn (Builder $query) => $query->withTenantsCount()->with('tenants:id,name,slug,is_active'))
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
                TextColumn::make('tenant_names')
                    ->label(config('sarasystem.tenant_plural_label'))
                    ->state(fn (User $record): array => $record->tenants->sortBy('name')->pluck('name')->all())
                    ->listWithLineBreaks()
                    ->wrap(),
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
                Filter::make('all_branches')
                    ->label('جستجو در همه شعبات')
                    ->toggle()
                    ->visible(static::canSearchAllBranches(...)),
                TextFilter::make('name')->label('نام'),
                TextFilter::make('mobile')->label('موبایل'),
                TextFilter::make('national_code')->label('کد ملی'),
                TextFilter::make('email')->label('ایمیل'),
            ])
            ->recordActions([
                Action::make('edit')
                    ->label(__('filament-actions::edit.single.label'))
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->url(function (User $record): ?string {
                        $tenant = static::manageableTenants($record)->first();

                        return $tenant ? UserResource::getUrl('edit', ['record' => $record], tenant: $tenant) : null;
                    })
                    ->visible(fn (Action $action): bool => filled($action->getUrl())),
                static::impersonateAction(),
                static::removeFromTenantAction(),
                // Deleting is done here, not on the edit page (ADR 0006).
                DeleteAction::make()
                    ->label('حذف حساب')
                    ->modalDescription('حساب کاربری از سامانه و از همه‌ی '.config('sarasystem.tenant_plural_label').' حذف می‌شود. برای برداشتن فقط از این '.config('sarasystem.tenant_label').' «حذف از '.config('sarasystem.tenant_label').'» را بزنید.')
                    ->authorize(static::canDeleteAccount(...)),
            ]);
    }

    private static function canSearchAllBranches(): bool
    {
        return Tenant::current()?->is_primary && UserResource::canViewAny();
    }

    private static function isCurrentTenantMember(User $record): bool
    {
        return $record->tenants->contains(Tenant::current()->getKey());
    }

    /** @return Collection<int, Tenant> */
    private static function manageableTenants(User $record, bool $forDeletion = false): Collection
    {
        $actor = auth()->user();
        $tenants = static::isCurrentTenantMember($record)
            ? collect([Tenant::current()])
            : $record->tenants->sortBy('name');

        return $tenants->filter(function (Tenant $tenant) use ($actor, $record, $forDeletion): bool {
            if (! $actor->canAccessTenant($tenant)) {
                return false;
            }

            return $tenant->execute(function () use ($actor, $record, $forDeletion): bool {
                $actor->unsetRelation('roles')->unsetRelation('permissions');

                try {
                    return UserResource::canEdit($record) && (! $forDeletion || UserResource::canDelete($record));
                } finally {
                    $actor->unsetRelation('roles')->unsetRelation('permissions');
                }
            });
        });
    }

    private static function canDeleteAccount(User $record): bool
    {
        $actor = auth()->user();

        return $record->isNot($actor)
            && ($actor->isSuperAdmin() || static::manageableTenants($record, forDeletion: true)->isNotEmpty());
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
            ->authorize(fn (User $record): bool => Impersonation::canImpersonate(auth()->user(), $record))
            ->action(function (User $record, ListUsers $livewire): void {
                $parameters = $livewire->tableFilters ? ['filters' => $livewire->tableFilters] : [];
                Impersonation::start(auth()->user(), $record, UserResource::getUrl('index', $parameters));

                // The panel root picks a tenant the user can open; the
                // current tenant may not be one of them.
                $panel = Filament::getPanel('admin');
                $livewire->redirect($record->canAccessPanel($panel) ? url($panel->getPath()) : route('dashboard'));
            });
    }

    /**
     * Removes the user's roles in the current or explicitly selected tenant.
     */
    public static function removeFromTenantAction(): Action
    {
        return Action::make('removeFromTenant')
            ->label('حذف از '.config('sarasystem.tenant_label'))
            ->icon(Heroicon::OutlinedUserMinus)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(fn (User $record): string => static::isCurrentTenantMember($record)
                ? "همه‌ی نقش‌های {$record->name} در «".Tenant::current()->name.'» گرفته می‌شود. حساب کاربر و عضویت او در جاهای دیگر باقی می‌ماند.'
                : "همه‌ی نقش‌های {$record->name} در ".config('sarasystem.tenant_label').' انتخاب‌شده گرفته می‌شود. حساب کاربر و عضویت او در جاهای دیگر باقی می‌ماند.')
            ->schema(fn (User $record): array => static::isCurrentTenantMember($record) ? [] : [
                Select::make('tenant_id')
                    ->label(config('sarasystem.tenant_label'))
                    ->options(fn (): array => static::manageableTenants($record)->pluck('name', 'id')->all())
                    ->required(),
            ])
            ->authorize(fn (User $record): bool => $record->isNot(auth()->user()) && static::manageableTenants($record)->isNotEmpty())
            ->action(function (User $record, array $data): void {
                $tenantId = $data['tenant_id'] ?? Tenant::current()->getKey();
                $tenant = static::manageableTenants($record)->firstWhere('id', $tenantId);
                abort_unless($record->isNot(auth()->user()) && $tenant, 403);

                $tenant->execute(fn () => $record->roles()->detach());
            });
    }
}
