<?php

namespace Modules\Student\Filament\Resources\Preregistrations\Schemas;

use App\Filament\CustomFields\CustomFieldAnswers;
use App\Filament\Workflow\CaseHistory;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Student\Enums\Nationality;
use Modules\Student\Models\Preregistration;

class PreregistrationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                ...self::components(),
                Section::make('فرایند پذیرش')
                    ->columns(4)
                    ->visible(fn (Preregistration $record): bool => $record->workflowCase !== null)
                    ->schema([
                        TextEntry::make('workflowCase.workflow_name')->label('فرایند'),
                        TextEntry::make('workflowCase.status')->label('وضعیت')->badge(),
                        TextEntry::make('current_step')
                            ->label('مرحله‌ی فعلی')
                            ->state(fn (Preregistration $record): ?string => $record->workflowCase?->currentStep()?->name)
                            ->placeholder('—'),
                        TextEntry::make('assignee')
                            ->label('در کارتابل')
                            ->state(fn (Preregistration $record): ?string => $record->workflowCase?->currentStep()?->assigneeLabel())
                            ->placeholder('—'),
                        CaseHistory::make('workflowCase.actions')->columnSpanFull(),
                    ]),
            ])
            ->columns(1);
    }

    /**
     * The preregistration's sections; also shown in the workflow cartable
     * (PreregistrationSubject).
     *
     * @return list<Section>
     */
    public static function components(): array
    {
        return [
            Section::make('پیش‌ثبت‌نام')
                ->columns(4)
                ->schema([
                    TextEntry::make('grade_title')->label('مقطع و پایه'),
                    TextEntry::make('preferredTenant.name')->label(config('sarasystem.tenant_label').' مورد نظر')->placeholder('—'),
                    TextEntry::make('status')->label('وضعیت')->badge(),
                    TextEntry::make('created_at')->label('تاریخ ثبت')->jalaliDateTime(),
                ]),
            Section::make('اطلاعات دانش‌آموز')
                ->columns(4)
                ->schema([
                    TextEntry::make('first_name')->label('نام'),
                    TextEntry::make('last_name')->label('نام خانوادگی'),
                    TextEntry::make('father_name')->label('نام پدر')->placeholder('—'),
                    TextEntry::make('gender')->label('جنسیت'),
                    TextEntry::make('nationality')->label('ملیت'),
                    TextEntry::make('national_code')
                        ->label(fn (Preregistration $record): string => $record->nationality?->nationalCodeLabel() ?? Nationality::Iranian->nationalCodeLabel())
                        ->copyable(),
                    TextEntry::make('birth_certificate_number')->label('شماره شناسنامه')->placeholder('—'),
                    TextEntry::make('birth_date')->label('تاریخ تولد')->jalaliDate()->placeholder('—'),
                    TextEntry::make('birth_place')->label('محل تولد')->placeholder('—'),
                ]),
            Section::make('اطلاعات تماس')
                ->columns(4)
                ->schema([
                    TextEntry::make('mobile')->label('تلفن همراه')->copyable(),
                    TextEntry::make('phone')->label('تلفن منزل')->placeholder('—'),
                    TextEntry::make('email')->label('ایمیل')->copyable()->placeholder('—'),
                    TextEntry::make('postal_code')->label('کد پستی')->placeholder('—'),
                    TextEntry::make('city')->label('شهر')->placeholder('—'),
                    TextEntry::make('address')->label('نشانی منزل')->placeholder('—')->columnSpan(3),
                ]),
            Section::make('سوابق تحصیلی')
                ->columns(4)
                ->schema([
                    TextEntry::make('previous_school')->label('مدرسه‌ی پیشین')->placeholder('—'),
                    TextEntry::make('last_average')->label('معدل ترم آخر')->placeholder('—'),
                ]),
            // Fields the organization added to the form (ADR 0017).
            CustomFieldAnswers::section('custom_fields'),
        ];
    }
}
