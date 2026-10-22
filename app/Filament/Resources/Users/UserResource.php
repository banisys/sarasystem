<?php

namespace App\Filament\Resources\Users;

use App\Authorization\RoleAssignment;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\Tenant;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Users of the panel's current tenant. Users are global, so the resource is
 * not Filament-scoped by ownership; membership (any role in the tenant)
 * limits the query instead, and roles are edited for the current tenant only.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'کاربر';

    protected static ?string $pluralModelLabel = 'کاربران';

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    /**
     * @return Builder<User>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->inTenant(Tenant::current());
    }

    /**
     * Besides the Update:User permission, the actor must be allowed to manage
     * this particular user (ADR 0003): every role the user holds anywhere must
     * be assignable by the actor, and super admins only by super admins.
     * Editing your own account is allowed; your own roles are locked in the form.
     */
    public static function canEdit(Model $record): bool
    {
        $actor = auth()->user();

        if (! $record->is($actor) && ! RoleAssignment::canManage($actor, $record, Tenant::current())) {
            return false;
        }

        return parent::canEdit($record);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
