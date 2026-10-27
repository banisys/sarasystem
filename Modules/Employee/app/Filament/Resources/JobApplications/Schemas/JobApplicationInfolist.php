<?php

namespace Modules\Employee\Filament\Resources\JobApplications\Schemas;

use App\Filament\CustomFields\CustomFieldAnswers;
use App\Filament\Workflow\CaseHistory;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Employee\Enums\EducationLevel;
use Modules\Employee\Enums\Nationality;
use Modules\Employee\Enums\UniversityType;
use Modules\Employee\Models\JobApplication;

class JobApplicationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                ...self::components(),
                Section::make('فرایند استخدام')
                    ->columns(4)
                    ->visible(fn (JobApplication $record): bool => $record->workflowCase !== null)
                    ->schema([
                        TextEntry::make('workflowCase.workflow_name')->label('فرایند'),
                        TextEntry::make('workflowCase.status')->label('وضعیت')->badge(),
                        TextEntry::make('current_step')
                            ->label('مرحله‌ی فعلی')
                            ->state(fn (JobApplication $record): ?string => $record->workflowCase?->currentStep()?->name)
                            ->placeholder('—'),
                        TextEntry::make('assignee')
                            ->label('در کارتابل')
                            ->state(fn (JobApplication $record): ?string => $record->workflowCase?->currentStep()?->assigneeLabel())
                            ->placeholder('—'),
                        CaseHistory::make('workflowCase.actions')->columnSpanFull(),
                    ]),
            ])
            ->columns(1);
    }

    /**
     * The application's sections; also shown in the workflow cartable
     * (JobApplicationSubject).
     *
     * @return list<Section>
     */
    public static function components(): array
    {
        return [
            Section::make('درخواست')
                ->columns(4)
                ->schema([
                    TextEntry::make('job_title')->label('شغل درخواستی'),
                    TextEntry::make('status')->label('وضعیت')->badge(),
                    TextEntry::make('created_at')->label('تاریخ درخواست')->jalaliDateTime(),
                    TextEntry::make('updated_at')->label('آخرین تغییر')->jalaliDateTime(),
                ]),
            Section::make('اطلاعات فردی')
                ->columns(4)
                ->schema([
                    TextEntry::make('first_name')->label('نام'),
                    TextEntry::make('last_name')->label('نام خانوادگی'),
                    TextEntry::make('father_name')->label('نام پدر')->placeholder('—'),
                    TextEntry::make('nationality')->label('ملیت'),
                    TextEntry::make('national_code')
                        ->label(fn (JobApplication $record): string => $record->nationality?->nationalCodeLabel() ?? Nationality::Iranian->nationalCodeLabel())
                        ->copyable(),
                    TextEntry::make('birth_date')->label('تاریخ تولد')->jalaliDate()->placeholder('—'),
                    TextEntry::make('gender')->label('جنسیت'),
                    TextEntry::make('military_status')->label('وضعیت نظام وظیفه')->placeholder('—'),
                ]),
            Section::make('اطلاعات تماس')
                ->columns(4)
                ->schema([
                    TextEntry::make('mobile')->label('تلفن همراه')->copyable(),
                    TextEntry::make('phone')->label('تلفن ثابت')->placeholder('—'),
                    TextEntry::make('email')->label('ایمیل')->copyable()->placeholder('—'),
                    TextEntry::make('postal_code')->label('کد پستی')->placeholder('—'),
                    TextEntry::make('address')->label('نشانی')->placeholder('—')->columnSpanFull(),
                ]),
            Section::make('سوابق تحصیلی')
                ->schema([
                    TextEntry::make('no_education')
                        ->hiddenLabel()
                        ->state('سابقه‌ی تحصیلی ثبت نشده است.')
                        ->visible(fn (JobApplication $record): bool => empty($record->education)),
                    RepeatableEntry::make('education')
                        ->hiddenLabel()
                        ->columns(4)
                        ->visible(fn (JobApplication $record): bool => ! empty($record->education))
                        ->schema([
                            TextEntry::make('level')->label('مقطع')
                                ->formatStateUsing(fn (?string $state): ?string => EducationLevel::tryFrom((string) $state)?->getLabel()),
                            TextEntry::make('major')->label('رشته'),
                            TextEntry::make('orientation')->label('گرایش')->placeholder('—'),
                            TextEntry::make('average')->label('معدل'),
                            TextEntry::make('rank')->label('رتبه کنکور')->placeholder('—'),
                            TextEntry::make('graduation_date')->label('تاریخ اخذ مدرک')->jalaliDate(),
                            TextEntry::make('university_type')->label('نوع دانشگاه')->placeholder('—')
                                ->formatStateUsing(fn (?string $state): ?string => UniversityType::tryFrom((string) $state)?->getLabel()),
                            TextEntry::make('university')->label('دانشگاه')->placeholder('—'),
                        ]),
                ]),
            Section::make('سوابق کاری')
                ->schema([
                    TextEntry::make('no_work_history')
                        ->hiddenLabel()
                        ->state('سابقه‌ی کاری ثبت نشده است.')
                        ->visible(fn (JobApplication $record): bool => empty($record->work_history)),
                    RepeatableEntry::make('work_history')
                        ->hiddenLabel()
                        ->columns(3)
                        ->visible(fn (JobApplication $record): bool => ! empty($record->work_history))
                        ->schema([
                            TextEntry::make('company_name')->label('محل کار'),
                            TextEntry::make('job_position')->label('سمت'),
                            TextEntry::make('company_phone')->label('تلفن محل کار')->placeholder('—'),
                            TextEntry::make('start_date')->label('شروع همکاری')->jalaliDate(),
                            TextEntry::make('end_date')->label('پایان همکاری')->jalaliDate()->placeholder('هنوز مشغول است'),
                            TextEntry::make('termination_reason')->label('علت پایان همکاری')->placeholder('—'),
                        ]),
                ]),
            // Fields the organization added to the form (ADR 0017).
            CustomFieldAnswers::section('custom_fields'),
        ];
    }
}
