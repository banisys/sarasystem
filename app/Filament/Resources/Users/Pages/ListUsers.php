<?php

namespace App\Filament\Resources\Users\Pages;

use App\Authorization\RoleAssignment;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\UserResource;
use App\Models\Tenant;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->addExistingUserAction(),
            CreateAction::make(),
        ];
    }

    /**
     * Users are global: someone already registered (e.g. staff of another
     * tenant, or a parent with children elsewhere) is added here by giving
     * them roles in this tenant, instead of creating a duplicate account.
     */
    protected function addExistingUserAction(): Action
    {
        return Action::make('addExistingUser')
            ->label('افزودن کاربر موجود')
            ->icon(Heroicon::OutlinedUserPlus)
            ->color('gray')
            ->visible(fn (): bool => UserResource::canCreate())
            ->schema([
                Select::make('user_id')
                    ->label('کاربر')
                    ->helperText('جستجو با نام یا ایمیل، بین کاربرانی که در این '.config('sarasystem.tenant_label').' عضو نیستند.')
                    ->searchable()
                    ->required()
                    ->getSearchResultsUsing(fn (string $search): array => User::query()
                        ->whereNot(fn ($query) => $query->inTenant(Tenant::current()))
                        ->where(fn ($query) => $query
                            ->where('name', 'ilike', "%{$search}%")
                            ->orWhere('email', 'ilike', "%{$search}%"))
                        ->orderBy('name')
                        ->limit(10)
                        ->get()
                        ->mapWithKeys(fn (User $user): array => [$user->id => "{$user->name} ({$user->email})"])
                        ->all())
                    ->getOptionLabelUsing(fn ($value): ?string => User::find($value)?->name),
                CheckboxList::make('roles')
                    ->label('نقش‌ها در این '.config('sarasystem.tenant_label'))
                    ->options(fn (): array => UserForm::assignableRoleOptions())
                    ->rule(fn (): Closure => UserForm::onlyAssignableRolesRule())
                    ->required()
                    ->columns(3),
            ])
            ->action(function (array $data): void {
                $user = User::findOrFail($data['user_id']);
                $roleIds = collect($data['roles'])
                    ->map(fn ($id): int => (int) $id)
                    ->intersect(RoleAssignment::assignableRoleIds(auth()->user(), Tenant::current()));

                $user->roles()->syncWithoutDetaching(
                    $roleIds->mapWithKeys(fn (int $roleId): array => [$roleId => ['tenant_id' => Tenant::current()->getKey()]])->all(),
                );

                Notification::make()
                    ->title("{$user->name} به «".Tenant::current()->name.'» اضافه شد.')
                    ->success()
                    ->send();
            });
    }
}
