<?php

namespace Modules\Employee\Filament\Resources\Employees\Tables;

use App\Filament\Support\TextFilter;
use App\Support\Identifiers;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Employee\Enums\EmploymentStatus;
use Modules\Employee\Enums\EmploymentType;

class EmployeesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user'))
            ->columns([
                TextColumn::make('user.name')
                    ->label('نام'),
                TextColumn::make('user.national_code')
                    ->label('کد ملی')
                    ->copyable(),
                TextColumn::make('user.mobile')
                    ->label('موبایل')
                    ->copyable(),
                TextColumn::make('personnel_code')
                    ->label('کد پرسنلی')
                    ->placeholder('—')
                    ->copyable(),
                TextColumn::make('job_title')
                    ->label('سمت')
                    ->sortable(),
                TextColumn::make('employment_type')
                    ->label('نوع همکاری'),
                TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge(),
                TextColumn::make('hired_at')
                    ->label('شروع به کار')
                    ->jalaliDate()
                    ->sortable(),
                TextColumn::make('left_at')
                    ->label('پایان همکاری')
                    ->jalaliDate()
                    ->placeholder('—')
                    ->toggledHiddenByDefault(),
            ])
            ->filters([
                Filter::make('person')
                    ->schema([TextInput::make('value')->label('نام، کد ملی یا موبایل')])
                    ->query(function (Builder $query, array $data): void {
                        $value = Identifiers::toLatinDigits(trim((string) ($data['value'] ?? '')));

                        if ($value !== '') {
                            $query->whereHas('user', fn (Builder $user) => $user
                                ->whereLike('name', "%{$value}%")
                                ->orWhereLike('national_code', "%{$value}%")
                                ->orWhereLike('mobile', "%{$value}%"));
                        }
                    }),
                TextFilter::make('personnel_code')->label('کد پرسنلی'),
                SelectFilter::make('status')
                    ->label('وضعیت')
                    ->options(EmploymentStatus::class),
                SelectFilter::make('employment_type')
                    ->label('نوع همکاری')
                    ->options(EmploymentType::class),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
