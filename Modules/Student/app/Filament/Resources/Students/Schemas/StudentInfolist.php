<?php

namespace Modules\Student\Filament\Resources\Students\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StudentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(config('sarasystem.learner_label'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('user.name')->label('نام'),
                        TextEntry::make('user.national_code')->label('کد ملی')->copyable(),
                        TextEntry::make('user.mobile')->label('موبایل')->copyable()->placeholder('—'),
                        TextEntry::make('student_code')->label('کد '.config('sarasystem.learner_label'))->placeholder('—'),
                        TextEntry::make('preregistration.program_title')->label('برنامه‌ی آموزشی')->placeholder('—'),
                        TextEntry::make('status')->label('وضعیت')->badge(),
                        TextEntry::make('enrolled_at')->label('تاریخ ثبت‌نام')->jalaliDate(),
                        TextEntry::make('left_at')->label('تاریخ خروج')->jalaliDate()->placeholder('—'),
                    ]),
            ])
            ->columns(1);
    }
}
