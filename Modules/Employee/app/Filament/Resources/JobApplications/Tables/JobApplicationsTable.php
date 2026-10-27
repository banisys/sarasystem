<?php

namespace Modules\Employee\Filament\Resources\JobApplications\Tables;

use App\Filament\Support\TextFilter;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Employee\Enums\ApplicationStatus;
use Modules\Employee\Filament\Actions\DownloadResumeAction;
use Modules\Employee\Models\JobApplication;

class JobApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('workflowCase'))
            ->columns([
                TextColumn::make('first_name')
                    ->label('نام')
                    ->state(fn ($record): string => $record->fullName())
                    ->sortable(['last_name', 'first_name']),
                TextColumn::make('national_code')
                    ->label('کد ملی')
                    ->copyable(),
                TextColumn::make('mobile')
                    ->label('موبایل')
                    ->copyable(),
                TextColumn::make('job_title')
                    ->label('شغل درخواستی')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->sortable(),
                TextColumn::make('current_step')
                    ->label('مرحله‌ی فعلی')
                    ->state(fn (JobApplication $record): ?string => $record->workflowCase?->currentStep()?->name)
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('تاریخ درخواست')
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
                    ->options(ApplicationStatus::class),
                SelectFilter::make('job_position_id')
                    ->label('شغل درخواستی')
                    ->relationship('jobPosition', 'title'),
                TextFilter::make('name')->label('نام')->attributes(['first_name', 'last_name']),
                TextFilter::make('national_code')->label('کد ملی'),
                TextFilter::make('mobile')->label('موبایل'),
            ])
            ->recordActions([
                ViewAction::make(),
                DownloadResumeAction::make(),
            ]);
    }
}
