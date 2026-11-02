<?php

namespace Modules\Class\Filament\Resources\Classrooms\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Class\Models\Classroom;
use Modules\Class\Support\ClassroomExtensions;

class ClassroomInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('مشخصات کلاس')
                    ->extraAttributes(['class' => 'classroom-details-section'])
                    ->columns(['md' => 2, 'xl' => 3])
                    ->schema([
                        TextEntry::make('title')->label('عنوان'),
                        TextEntry::make('code')->label('کد')->placeholder('—'),
                        TextEntry::make('term.title')->label('دوره‌ی آموزشی')->placeholder('—'),
                        TextEntry::make('mode')->label('برگزاری')->badge(),
                        TextEntry::make('attendees')
                            ->label('شرکت‌کنندگان')
                            ->state(fn (Classroom $record): string => $record->attendeeCount().($record->capacity !== null ? ' از '.$record->capacity : '')),
                        TextEntry::make('is_active')->label('وضعیت')->formatStateUsing(fn (bool $state): string => $state ? 'فعال' : 'غیرفعال')->badge()->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                        TextEntry::make('parent.title')->label('زیرکلاسِ')->placeholder('—'),
                        TextEntry::make('children.title')->label('زیرکلاس‌ها')->badge()->placeholder('—'),
                        // Entries other modules add (ADR 0031).
                        ...ClassroomExtensions::components('infolist'),
                    ]),
                Section::make('برگزاری کلاس')
                    ->extraAttributes(['class' => 'classroom-details-section'])
                    ->columns(['md' => 2])
                    ->schema([
                        TextEntry::make('starts_on')->label('تاریخ شروع')->jalaliDate()->placeholder('—'),
                        TextEntry::make('ends_on')->label('تاریخ پایان')->jalaliDate()->placeholder('—'),
                        TextEntry::make('location')->label('مکان')->placeholder('—'),
                        TextEntry::make('meeting_url')->label('لینک کلاس آنلاین')->url(fn (Classroom $record): ?string => $record->meeting_url, shouldOpenInNewTab: true)->color('primary')->placeholder('—'),
                    ]),
                Section::make('توضیحات')
                    ->extraAttributes(['class' => 'classroom-details-section'])
                    ->schema([
                        TextEntry::make('description')->hiddenLabel()->placeholder('توضیحی ثبت نشده است.'),
                    ]),
            ])
            ->columns(1);
    }
}
