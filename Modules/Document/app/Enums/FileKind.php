<?php

namespace Modules\Document\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Which files a document type accepts.
 */
enum FileKind: string implements HasLabel
{
    case Image = 'image';

    case Pdf = 'pdf';

    case ImageOrPdf = 'image_or_pdf';

    public function getLabel(): string
    {
        return match ($this) {
            self::Image => 'تصویر (JPG، PNG)',
            self::Pdf => 'PDF',
            self::ImageOrPdf => 'تصویر یا PDF',
        };
    }

    /**
     * @return list<string>
     */
    public function extensions(): array
    {
        return match ($this) {
            self::Image => ['jpg', 'jpeg', 'png'],
            self::Pdf => ['pdf'],
            self::ImageOrPdf => ['jpg', 'jpeg', 'png', 'pdf'],
        };
    }

    /**
     * @return list<string>
     */
    public function mimeTypes(): array
    {
        return match ($this) {
            self::Image => ['image/jpeg', 'image/png'],
            self::Pdf => ['application/pdf'],
            self::ImageOrPdf => ['image/jpeg', 'image/png', 'application/pdf'],
        };
    }

    /**
     * For an `<input type="file" accept>`.
     */
    public function accept(): string
    {
        return implode(',', [...$this->mimeTypes(), ...array_map(fn (string $extension): string => ".{$extension}", $this->extensions())]);
    }
}
