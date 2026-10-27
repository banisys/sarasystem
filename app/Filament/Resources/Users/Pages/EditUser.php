<?php

namespace App\Filament\Resources\Users\Pages;

use App\Auth\Impersonation;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('impersonate')
                ->label('ورود با این کاربر')
                ->icon(Heroicon::OutlinedArrowRightEndOnRectangle)
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('تا وقتی «بازگشت به حساب خودم» را نزنید، همه‌ی کارها به نام این کاربر انجام می‌شود.')
                ->visible(fn (User $record): bool => Impersonation::canImpersonate(auth()->user(), $record))
                ->action(function (User $record): void {
                    Impersonation::start(auth()->user(), $record, UserResource::getUrl('edit', ['record' => $record]));

                    // The panel root picks a tenant the user can open; the
                    // current tenant may not be one of them.
                    $panel = Filament::getPanel('admin');
                    $this->redirect($record->canAccessPanel($panel) ? url($panel->getPath()) : route('dashboard'));
                }),
        ];
    }
}
