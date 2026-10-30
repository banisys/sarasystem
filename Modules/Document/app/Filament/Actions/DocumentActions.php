<?php

namespace Modules\Document\Filament\Actions;

use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Support\Icons\Heroicon;
use Modules\Document\Enums\DocumentStatus;
use Modules\Document\Models\Document;
use Modules\Document\Models\DocumentType;
use Modules\Document\Services\Documents;

/**
 * What can be done to one document in the documents list (ADR 0019).
 * Uploading and checking follow the type's roles in the current tenant.
 */
class DocumentActions
{
    /** Filament's temporary uploads, moved into the media library on save. */
    public const UPLOAD_DIRECTORY = 'document-uploads';

    public static function files(): Action
    {
        return Action::make('files')
            ->label('مشاهده‌ی فایل‌ها')
            ->icon(Heroicon::OutlinedPaperClip)
            ->modalHeading(fn (Document $record): string => "{$record->documentType->title} — {$record->personName()}")
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('بستن')
            ->schema(fn (Document $record): array => self::fileEntries($record));
    }

    public static function approve(): Action
    {
        return Action::make('approve')
            ->label('تأیید مدرک')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (Document $record): bool => $record->status !== DocumentStatus::Approved && self::canVerify($record))
            ->action(function (Document $record, Documents $documents): void {
                $documents->approve($record, self::user());
                Notification::make()->title('مدرک تأیید شد')->success()->send();
            });
    }

    public static function reject(): Action
    {
        return Action::make('reject')
            ->label('رد مدرک')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (Document $record): bool => $record->status !== DocumentStatus::Rejected && self::canVerify($record))
            ->schema([
                Textarea::make('reason')->label('علت رد')->helperText('به کسی که مدرک را دوباره بارگذاری می‌کند نشان داده می‌شود.')->rows(2)->required(),
            ])
            ->action(function (Document $record, array $data, Documents $documents): void {
                $documents->reject($record, self::user(), $data['reason']);
                Notification::make()->title('مدرک رد شد')->success()->send();
            });
    }

    public static function replace(): Action
    {
        return Action::make('replace')
            ->label('بارگذاری دوباره')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->visible(fn (Document $record): bool => self::canUpload($record->documentType))
            ->schema(fn (Document $record): array => self::uploadFields($record->documentType))
            ->action(function (Document $record, array $data, Documents $documents): void {
                $documents->upload($record->documentable, $record->documentType, (array) $data['files'], self::expiry($data), self::user());
                Notification::make()->title('مدرک بارگذاری شد و منتظر بررسی است')->success()->send();
            });
    }

    /**
     * File input (and expiry date) for one type.
     *
     * @return list<Component|FileUpload|DatePicker>
     */
    public static function uploadFields(?DocumentType $type, string $prefix = ''): array
    {
        if ($type === null) {
            return [];
        }

        return [
            FileUpload::make($prefix.'files')
                ->label('فایل‌ها')
                ->helperText($type->file_kind->getLabel().'، حداکثر '.$type->max_files.' فایل، هر فایل تا '.round($type->max_kilobytes / 1024, 1).' مگابایت')
                ->disk('local')
                ->directory(self::UPLOAD_DIRECTORY)
                ->visibility('private')
                ->multiple()
                ->maxFiles($type->max_files)
                ->acceptedFileTypes($type->file_kind->mimeTypes())
                ->maxSize($type->max_kilobytes)
                ->required(),
            DatePicker::make($prefix.'expires_at')
                ->label('تاریخ انقضا')
                ->jalali()
                ->visible($type->has_expiry)
                ->required($type->has_expiry),
        ];
    }

    /**
     * Links to the document's files, its status and dates.
     *
     * @return list<TextEntry>
     */
    public static function fileEntries(Document $document): array
    {
        $entries = [];

        foreach ($document->fileLinks() as $index => $link) {
            $entries[] = TextEntry::make("file_{$document->getKey()}_{$index}")
                ->hiddenLabel()
                ->state($link['name'])
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->url($link['url'], shouldOpenInNewTab: true)
                ->color('primary');
        }

        $entries[] = TextEntry::make("status_{$document->getKey()}")
            ->label('وضعیت')
            ->state($document->status)
            ->badge()
            ->helperText($document->status === DocumentStatus::Rejected ? "علت: {$document->rejection_reason}" : null);

        if ($document->expires_at !== null) {
            $entries[] = TextEntry::make("expires_{$document->getKey()}")
                ->label('تاریخ انقضا')
                ->state($document->expires_at)
                ->jalaliDate()
                ->color($document->isExpired() ? 'danger' : ($document->isExpiring() ? 'warning' : null));
        }

        return $entries;
    }

    /**
     * Active types of the holder (or of any holder) the user may upload here.
     *
     * @return array<int, string>
     */
    public static function uploadableTypes(?string $holder): array
    {
        return DocumentType::definedTypes()
            ->active()
            ->ordered()
            ->get()
            ->filter(fn (DocumentType $type): bool => self::canUpload($type))
            ->pluck('title', 'id')
            ->all();
    }

    /**
     * The expiry date picked (Filament's Jalali picker keeps Gregorian `Y-m-d`).
     *
     * @param  array<string, mixed>  $data
     */
    public static function expiry(array $data, string $prefix = ''): ?string
    {
        $value = $data[$prefix.'expires_at'] ?? null;

        return filled($value) ? substr((string) $value, 0, 10) : null;
    }

    public static function canUpload(?DocumentType $type, ?Tenant $tenant = null): bool
    {
        $tenant ??= Filament::getTenant();

        return $type !== null && $tenant instanceof Tenant && $type->allowsUpload(self::user(), $tenant);
    }

    public static function canVerify(Document $document): bool
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant && $document->documentType->allowsVerify(self::user(), $tenant);
    }

    private static function user(): User
    {
        /** @var User */
        return auth()->user();
    }
}
