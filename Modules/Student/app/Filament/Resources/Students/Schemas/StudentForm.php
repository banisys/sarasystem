<?php

namespace Modules\Student\Filament\Resources\Students\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Modules\Student\Enums\StudentStatus;

/**
 * Membership details only; the person's identity is edited on the user.
 */
class StudentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('student_code')
                    ->label('کد '.config('sarasystem.learner_label'))
                    ->maxLength(20)
                    ->unique(ignoreRecord: true),
                Select::make('status')
                    ->label('وضعیت')
                    ->options(StudentStatus::class)
                    ->required()
                    ->live(),
                DatePicker::make('enrolled_at')
                    ->label('تاریخ ثبت‌نام')
                    ->jalali()
                    ->required(),
                DatePicker::make('left_at')
                    ->label('تاریخ خروج')
                    ->jalali()
                    ->afterOrEqual('enrolled_at')
                    ->visible(fn (Get $get): bool => self::hasLeft($get('status')))
                    ->required(fn (Get $get): bool => self::hasLeft($get('status'))),
            ])
            ->columns(2);
    }

    private static function hasLeft(mixed $status): bool
    {
        $status = $status instanceof StudentStatus ? $status : StudentStatus::tryFrom((string) $status);

        return $status?->hasLeft() ?? false;
    }
}
