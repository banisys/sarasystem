<?php

namespace Modules\Student\Filament\Resources\Programs\Tables;

use App\Filament\CustomFields\CustomizeFormAction;
use App\Filament\Support\TextFilter;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\Student\Forms\PreregistrationForm;

class ProgramsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['workflow', 'availableInTenants'])->withCount('preregistrations'))
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                TextColumn::make('title')
                    ->label('عنوان')
                    ->sortable(),
                TextColumn::make('availability')
                    ->label(fn (): string => config('sarasystem.tenant_plural_label').' ارائه‌دهنده')
                    ->state(fn ($record): string => $record->all_tenants
                        ? 'همهٔ '.config('sarasystem.tenant_plural_label')
                        : $record->availableInTenants->pluck('name')->join('، '))
                    ->wrap(),
                IconColumn::make('is_active')
                    ->label('در فرم')
                    ->boolean(),
                TextColumn::make('workflow.name')
                    ->label('فرایند پذیرش')
                    ->placeholder('بدون فرایند'),
                TextColumn::make('preregistrations_count')
                    ->label('تعداد پیش‌ثبت‌نام‌ها')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('آخرین تغییر')
                    ->jalaliDateTime()
                    ->sortable(),
            ])
            ->filters([
                TextFilter::make('title')->label('عنوان'),
                TernaryFilter::make('is_active')->label('در فرم'),
            ])
            ->recordActions([
                EditAction::make(),
                // The form's setup for this program only (ADR 0017).
                CustomizeFormAction::make('customizeForm', new PreregistrationForm, perRecord: true)
                    ->authorize('update'),
                DeleteAction::make()
                    ->modalDescription('پیش‌ثبت‌نام‌هایی که برای این برنامه ثبت شده‌اند حذف نمی‌شوند و عنوان برنامه در آن‌ها می‌ماند.'),
            ]);
    }
}
