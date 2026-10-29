<?php

namespace Modules\Contract\Filament\Actions;

use App\Filament\CustomFields\CustomFieldInputs;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Modules\Contract\Enums\ContractStatus;
use Modules\Contract\Forms\ContractForm;
use Modules\Contract\Models\Contract;
use Modules\Contract\Models\ContractType;
use Modules\Contract\Services\ContractException;
use Modules\Contract\Services\ContractRenderer;
use Modules\Contract\Services\Contracts;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * What can be done to one contract in the contracts list (ADR 0020), and
 * the terms fields shared with the cartable.
 */
class ContractActions
{
    public const UPLOAD_DIRECTORY = 'contract-uploads';

    /**
     * The terms of a contract of the type: the built-in fields it asks and
     * its added fields (ADR 0017).
     *
     * @return list<Field>
     */
    public static function termsFields(?ContractType $type): array
    {
        if ($type === null) {
            return [];
        }

        $form = ContractForm::for($type);

        return array_values(array_filter([
            $form->asks('starts_on') ? DatePicker::make('starts_on')->label('تاریخ شروع')->jalali()->required($form->requires('starts_on')) : null,
            $form->asks('ends_on') ? DatePicker::make('ends_on')->label('تاریخ پایان')->jalali()->required($form->requires('ends_on')) : null,
            $form->asks('amount') ? TextInput::make('amount')->label('مبلغ (ریال)')->integer()->minValue(0)->required($form->requires('amount')) : null,
            ...CustomFieldInputs::components($form),
        ]));
    }

    public static function preview(): Action
    {
        return Action::make('preview')
            ->label('متن قرارداد')
            ->icon(Heroicon::OutlinedDocumentText)
            ->modalHeading(fn (Contract $record): string => $record->contractType->title)
            ->modalWidth('4xl')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('بستن')
            ->modalContent(fn (Contract $record): HtmlString => self::html($record));
    }

    public static function pdf(): Action
    {
        return Action::make('pdf')
            ->label('دریافت PDF')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->action(fn (Contract $record, ContractRenderer $renderer): StreamedResponse => response()->streamDownload(
                function () use ($record, $renderer): void {
                    echo $renderer->pdf($record);
                },
                $renderer->fileName($record),
                ['Content-Type' => 'application/pdf'],
            ));
    }

    public static function edit(): Action
    {
        return Action::make('editTerms')
            ->label('ویرایش')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->authorize('update')
            ->visible(fn (Contract $record): bool => $record->isDraft())
            ->fillForm(fn (Contract $record): array => Contracts::state($record))
            ->schema(fn (Contract $record): array => self::termsFields($record->contractType))
            ->action(function (Contract $record, array $data, Contracts $contracts): void {
                self::attempt(fn () => $contracts->saveDraft($record, $record->contractType, $record->contractable, $data, self::user()), 'قرارداد ذخیره شد');
            });
    }

    public static function issue(): Action
    {
        return Action::make('issue')
            ->label('صدور')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('success')
            ->authorize('update')
            ->visible(fn (Contract $record): bool => $record->isDraft() && ! $record->isForApplicant())
            ->requiresConfirmation()
            ->modalDescription('با صدور، متن قرارداد ثابت می‌شود و شماره می‌گیرد؛ بعد از آن قابل ویرایش نیست. اگر امضای الکترونیکی مجاز باشد، به شخص پیامک می‌رود.')
            ->action(function (Contract $record, Contracts $contracts): void {
                self::attempt(fn () => $contracts->issue($record, self::user()), 'قرارداد صادر شد');
            });
    }

    public static function recordPaperSignature(): Action
    {
        return Action::make('paperSignature')
            ->label('ثبت امضای کاغذی')
            ->icon(Heroicon::OutlinedPencil)
            ->color('success')
            ->authorize('update')
            ->visible(fn (Contract $record): bool => $record->canBeSignedOnPaper())
            ->modalDescription('نسخه‌ی چاپی قرارداد را شخص امضا کرده است؛ تصویر یا PDF نسخه‌ی امضاشده را بارگذاری کنید.')
            ->schema([
                FileUpload::make('scan')
                    ->label('نسخه‌ی امضاشده')
                    ->disk('local')
                    ->directory(self::UPLOAD_DIRECTORY)
                    ->visibility('private')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'application/pdf'])
                    ->maxSize(10240)
                    ->required(),
            ])
            ->action(function (Contract $record, array $data, Contracts $contracts): void {
                self::attempt(fn () => $contracts->recordPaperSignature($record, self::user(), (string) $data['scan']), 'امضای قرارداد ثبت شد');
            });
    }

    public static function scan(): Action
    {
        return Action::make('scan')
            ->label('نسخه‌ی امضاشده')
            ->icon(Heroicon::OutlinedPaperClip)
            ->visible(fn (Contract $record): bool => $record->hasMedia(Contract::SCAN_COLLECTION))
            ->action(fn (Contract $record): ?StreamedResponse => $record->getFirstMedia(Contract::SCAN_COLLECTION)?->toResponse(request()));
    }

    public static function cancel(): Action
    {
        return Action::make('cancel')
            ->label('لغو قرارداد')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->authorize('update')
            ->visible(fn (Contract $record): bool => in_array($record->status, [ContractStatus::Issued, ContractStatus::Signed], true))
            ->schema([
                Textarea::make('reason')->label('علت لغو')->rows(2)->required()->maxLength(1000),
            ])
            ->action(function (Contract $record, array $data, Contracts $contracts): void {
                self::attempt(fn () => $contracts->cancel($record, self::user(), $data['reason']), 'قرارداد لغو شد');
            });
    }

    /**
     * The contract's text and signature block, for a modal.
     */
    public static function html(Contract $contract): HtmlString
    {
        return new HtmlString('<div class="fi-prose" dir="rtl">'.app(ContractRenderer::class)->document($contract).'</div>');
    }

    /**
     * Runs a contract operation; a refusal becomes a notification.
     */
    public static function attempt(callable $operation, string $success): void
    {
        try {
            $operation();
        } catch (ContractException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title($success)->success()->send();
    }

    private static function user(): User
    {
        /** @var User */
        return auth()->user();
    }
}
