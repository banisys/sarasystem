<?php

namespace Modules\Student\Filament\Resources\Students\RelationManagers;

use App\Guardians\GuardianException;
use App\Guardians\GuardianRelationship;
use App\Guardians\Guardians;
use App\Models\Guardianship;
use App\Models\Tenant;
use App\Models\User;
use App\Rules\IranianMobile;
use App\Rules\NationalCode;
use App\Support\Identifiers;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Student\Models\Student;

/**
 * The «اولیا» tab of a learner (ADR 0027): their parents and guardians.
 * A guardian is found by national code or made from the form, gets the
 * guardian role here, and then sees the learner's classes in the user
 * panel. Whoever may edit the learner manages them.
 */
class GuardiansRelationManager extends RelationManager
{
    protected static string $relationship = 'guardianships';

    protected static ?string $title = 'اولیا';

    protected static ?string $modelLabel = 'ولی';

    protected static ?string $pluralModelLabel = 'اولیا';

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('view', $ownerRecord) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('guardian'))
            ->columns([
                TextColumn::make('guardian.name')
                    ->label('نام'),
                TextColumn::make('relationship')
                    ->label('نسبت')
                    ->badge(),
                TextColumn::make('guardian.national_code')
                    ->label('کد ملی')
                    ->copyable(),
                TextColumn::make('guardian.mobile')
                    ->label('موبایل')
                    ->placeholder('—')
                    ->copyable(),
                IconColumn::make('receives_sms')
                    ->label('پیامک')
                    ->boolean(),
            ])
            ->headerActions([
                Action::make('addGuardian')
                    ->label('افزودن ولی')
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->visible(fn (): bool => $this->canManage())
                    ->schema([
                        TextInput::make('national_code')
                            ->label('کد ملی ولی')
                            ->helperText('اگر این کد ملی در سامانه باشد، همان کاربر ولی می‌شود و نام و موبایل لازم نیست.')
                            ->required()
                            ->rule(new NationalCode)
                            ->dehydrateStateUsing(fn (?string $state): string => Identifiers::toLatinDigits(trim((string) $state))),
                        TextInput::make('name')
                            ->label('نام و نام خانوادگی')
                            ->maxLength(255),
                        TextInput::make('mobile')
                            ->label('موبایل')
                            ->rule(new IranianMobile)
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? (Identifiers::normalizeMobile(Identifiers::toLatinDigits($state)) ?? $state) : null),
                        Select::make('relationship')
                            ->label('نسبت')
                            ->options(GuardianRelationship::class)
                            ->default(GuardianRelationship::Father->value)
                            ->required(),
                        Toggle::make('receives_sms')
                            ->label('پیامک‌های مربوط به '.config('sarasystem.learner_label').' را بگیرد')
                            ->default(true),
                    ])
                    ->action(function (array $data, Guardians $guardians): void {
                        $existing = User::query()->where('national_code', $data['national_code'])->exists();

                        if (! $existing && (blank($data['name'] ?? null) || blank($data['mobile'] ?? null))) {
                            Notification::make()->title('کاربری با این کد ملی نیست؛ نام و موبایل ولی را وارد کنید.')->danger()->send();

                            return;
                        }

                        $this->attempt(fn () => $guardians->link(
                            $this->learner()->user,
                            $guardians->findOrCreateGuardian($data),
                            GuardianRelationship::from($data['relationship'] instanceof GuardianRelationship ? $data['relationship']->value : $data['relationship']),
                            (bool) ($data['receives_sms'] ?? true),
                            $this->tenant(),
                            $this->actor(),
                        ), 'ولی اضافه شد.');
                    }),
            ])
            ->recordActions([
                Action::make('editGuardian')
                    ->label('ویرایش')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->visible(fn (): bool => $this->canManage())
                    ->fillForm(fn (Guardianship $record): array => ['relationship' => $record->relationship->value, 'receives_sms' => $record->receives_sms])
                    ->schema([
                        Select::make('relationship')->label('نسبت')->options(GuardianRelationship::class)->required(),
                        Toggle::make('receives_sms')->label('پیامک‌ها را بگیرد'),
                    ])
                    ->action(fn (array $data, Guardianship $record) => $record->update([
                        'relationship' => $data['relationship'],
                        'receives_sms' => (bool) $data['receives_sms'],
                    ])),
                Action::make('removeGuardian')
                    ->label('حذف')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('اگر ولیِ کس دیگری در این '.config('sarasystem.tenant_label').' نباشد، نقش ولی هم از او گرفته می‌شود.')
                    ->visible(fn (): bool => $this->canManage())
                    ->action(fn (Guardianship $record, Guardians $guardians) => $guardians->unlink($record, $this->tenant())),
            ]);
    }

    private function attempt(callable $operation, string $success): void
    {
        try {
            $operation();
        } catch (GuardianException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title($success)->success()->send();
    }

    private function canManage(): bool
    {
        return auth()->user()?->can('update', $this->learner()) ?? false;
    }

    private function learner(): Student
    {
        /** @var Student */
        return $this->getOwnerRecord();
    }

    private function tenant(): Tenant
    {
        /** @var Tenant */
        return Filament::getTenant();
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }
}
