<?php

namespace Modules\Employee\Filament\Resources\Employees\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Modules\Employee\Enums\EmploymentStatus;
use Modules\Employee\Enums\EmploymentType;

/**
 * Employment details only; the person's identity is edited on the user.
 */
class EmployeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('job_title')
                    ->label('سمت')
                    ->required()
                    ->maxLength(255),
                TextInput::make('personnel_code')
                    ->label('کد پرسنلی')
                    ->maxLength(20)
                    ->unique(ignoreRecord: true),
                Select::make('employment_type')
                    ->label('نوع همکاری')
                    ->options(EmploymentType::class)
                    ->required(),
                Select::make('status')
                    ->label('وضعیت')
                    ->options(EmploymentStatus::class)
                    ->required()
                    ->live(),
                DatePicker::make('hired_at')
                    ->label('تاریخ شروع به کار')
                    ->jalali()
                    ->required(),
                DatePicker::make('left_at')
                    ->label('تاریخ پایان همکاری')
                    ->jalali()
                    ->afterOrEqual('hired_at')
                    ->visible(fn (Get $get): bool => in_array($get('status'), [EmploymentStatus::Left, EmploymentStatus::Left->value], true))
                    ->required(fn (Get $get): bool => in_array($get('status'), [EmploymentStatus::Left, EmploymentStatus::Left->value], true)),
            ])
            ->columns(2);
    }
}
