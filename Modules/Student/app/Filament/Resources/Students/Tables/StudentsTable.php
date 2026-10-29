<?php

namespace Modules\Student\Filament\Resources\Students\Tables;

use App\Filament\Support\TextFilter;
use App\Support\Identifiers;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Student\Enums\StudentStatus;

class StudentsTable
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
                TextColumn::make('student_code')
                    ->label('کد '.config('sarasystem.learner_label'))
                    ->placeholder('—')
                    ->copyable(),
                TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge(),
                TextColumn::make('enrolled_at')
                    ->label('تاریخ ثبت‌نام')
                    ->jalaliDate()
                    ->sortable(),
                TextColumn::make('left_at')
                    ->label('تاریخ خروج')
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
                TextFilter::make('student_code')->label('کد '.config('sarasystem.learner_label')),
                SelectFilter::make('status')
                    ->label('وضعیت')
                    ->options(StudentStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
