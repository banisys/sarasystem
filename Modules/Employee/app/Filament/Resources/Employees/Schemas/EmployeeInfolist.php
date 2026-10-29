<?php

namespace Modules\Employee\Filament\Resources\Employees\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EmployeeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('کارمند')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('user.name')->label('نام'),
                        TextEntry::make('user.national_code')->label('کد ملی')->copyable(),
                        TextEntry::make('user.mobile')->label('موبایل')->copyable()->placeholder('—'),
                        TextEntry::make('personnel_code')->label('کد پرسنلی')->placeholder('—'),
                        TextEntry::make('job_title')->label('سمت'),
                        TextEntry::make('employment_type')->label('نوع همکاری'),
                        TextEntry::make('status')->label('وضعیت')->badge(),
                        TextEntry::make('hired_at')->label('شروع به کار')->jalaliDate(),
                        TextEntry::make('left_at')->label('پایان همکاری')->jalaliDate()->placeholder('—'),
                    ]),
            ])
            ->columns(1);
    }
}
