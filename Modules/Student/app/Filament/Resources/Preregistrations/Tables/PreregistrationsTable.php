<?php

namespace Modules\Student\Filament\Resources\Preregistrations\Tables;

use App\Filament\Support\TextFilter;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Student\Enums\PreregistrationStatus;
use Modules\Student\Models\Preregistration;

class PreregistrationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['workflowCase', 'preferredTenant']))
            ->columns([
                TextColumn::make('first_name')
                    ->label('نام')
                    ->state(fn (Preregistration $record): string => $record->fullName())
                    ->sortable(['last_name', 'first_name']),
                TextColumn::make('national_code')
                    ->label('کد ملی')
                    ->copyable(),
                TextColumn::make('mobile')
                    ->label('موبایل')
                    ->copyable(),
                TextColumn::make('program_title')
                    ->label('برنامه‌ی آموزشی')
                    ->sortable(),
                TextColumn::make('preferredTenant.name')
                    ->label(config('sarasystem.tenant_label').' مورد نظر')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->sortable(),
                TextColumn::make('current_step')
                    ->label('مرحله‌ی فعلی')
                    ->state(fn (Preregistration $record): ?string => $record->workflowCase?->currentStep()?->name)
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('تاریخ ثبت')
                    ->jalaliDateTime()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('آخرین تغییر')
                    ->jalaliDateTime()
                    ->sortable()
                    ->toggledHiddenByDefault(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('وضعیت')
                    ->options(PreregistrationStatus::class),
                SelectFilter::make('program_id')
                    ->label('برنامه‌ی آموزشی')
                    ->relationship('program', 'title'),
                TextFilter::make('name')->label('نام')->attributes(['first_name', 'last_name']),
                TextFilter::make('national_code')->label('کد ملی'),
                TextFilter::make('mobile')->label('موبایل'),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
