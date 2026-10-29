<?php

namespace Modules\Contract\Services;

use App\CustomFields\CustomField;
use App\Support\PersianNumberWords;
use App\Support\PersianPdf;
use Ariaieboy\Jalali\CalendarUtils;
use Carbon\CarbonInterface;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Modules\Contract\Enums\ContractKind;
use Modules\Contract\Enums\SignedVia;
use Modules\Contract\Models\Contract;

/**
 * A contract's text (ADR 0020): the type's text with its merge tags filled
 * in, fixed when the contract is issued; the signature block under it;
 * and the PDF of both.
 */
class ContractRenderer
{
    /** Printed for a value that is not known, to be filled in by hand. */
    public const BLANK = '..................';

    /**
     * Characters Shabnam has no glyph for; mPDF can't take them from
     * another font while it shapes Persian text.
     */
    private const PDF_REPLACEMENTS = ['—' => '-', '–' => '-'];

    private const STYLES = <<<'CSS'
        <style>
            body { font-family: shabnam; font-size: 11pt; line-height: 1.9; }
            .contract-title { text-align: center; font-size: 15pt; }
            .contract-number { text-align: left; font-size: 9pt; }
            .contract-draft { text-align: center; color: #b91c1c; font-weight: bold; }
            .contract-body table { border-collapse: collapse; width: 100%; }
            .contract-body td, .contract-body th { border: 1px solid #555; padding: 4px; }
            .contract-signature { margin-top: 24pt; border-top: 1px solid #999; font-size: 9pt; }
        </style>
        CSS;

    /**
     * The text: as issued, or (for a draft) from the type's current text.
     */
    public function body(Contract $contract): string
    {
        if ($contract->body !== null) {
            return $contract->body;
        }

        return RichContentRenderer::make($contract->contractType->body ?? '')
            ->mergeTags($this->values($contract))
            ->toHtml();
    }

    /**
     * The text, the signature block and, for a draft, a «پیش‌نویس» mark,
     * as one HTML fragment.
     */
    public function document(Contract $contract): string
    {
        $title = e($contract->contractType->title);
        $number = $contract->number === null ? '' : '<p class="contract-number">شماره: '.e($contract->number).'</p>';
        $parent = match (true) {
            $contract->parent === null => '',
            $contract->kind === ContractKind::Amendment => '<p class="contract-number">الحاقیه‌ی قرارداد '.e((string) $contract->parent->number).'</p>',
            default => '<p class="contract-number">در ادامه‌ی قرارداد '.e((string) $contract->parent->number).'</p>',
        };
        $draft = $contract->isDraft() ? '<p class="contract-draft">پیش‌نویس</p>' : '';

        return "{$draft}<h1 class=\"contract-title\">{$title}</h1>{$number}{$parent}<div class=\"contract-body\">{$this->body($contract)}</div>{$this->signatureBlock($contract)}";
    }

    /**
     * How the contract was signed, or that it is not signed yet.
     */
    public function signatureBlock(Contract $contract): string
    {
        $lines = match (true) {
            $contract->signed_via === SignedVia::Electronic => [
                'امضای الکترونیکی: '.e($contract->personName()),
                'با کد پیامکی به موبایل '.e((string) $contract->signed_mobile).' در '.self::dateTime($contract->signed_at),
            ],
            $contract->signed_via === SignedVia::Paper => [
                'امضای کاغذی، ثبت‌شده در '.self::dateTime($contract->signed_at).' توسط '.e((string) $contract->signatureRecordedBy?->name),
            ],
            default => ['امضای '.e($contract->personName()).': '.self::BLANK],
        };

        if ($contract->body_hash !== null) {
            $lines[] = '<span dir="ltr">SHA-256: '.$contract->body_hash.'</span>';
        }

        return '<div class="contract-signature"><p>'.implode('</p><p>', $lines).'</p></div>';
    }

    /**
     * Values of the type's merge tags; unknown ones are blanks to fill in
     * by hand.
     *
     * @return array<string, string>
     */
    public function values(Contract $contract): array
    {
        $type = $contract->contractType;
        $record = $contract->contractable;
        $party = $contract->partyDescriptor();

        $values = [
            'contract_title' => $type->title,
            'contract_number' => $contract->number,
            'issued_on' => self::date($contract->issued_at ?? now()),
            'tenant_name' => $contract->tenant?->name,
            'starts_on' => self::date($contract->starts_on),
            'ends_on' => self::date($contract->ends_on),
            'amount' => $contract->amount === null ? null : number_format($contract->amount),
            'amount_words' => $contract->amount === null ? null : PersianNumberWords::convert($contract->amount).' ریال',
            'parent_number' => $contract->parent?->number,
            'parent_title' => $contract->parent?->contractType->title,
            'parent_starts_on' => self::date($contract->parent?->starts_on),
            'parent_ends_on' => self::date($contract->parent?->ends_on),
            ...($record !== null && $party !== null ? $party->values($record) : []),
        ];

        foreach ($contract->answers ?? [] as $answer) {
            $values[$answer['key']] = CustomField::fromArray($answer)->display($answer['value'] ?? null);
        }

        return collect($type->mergeTags())
            ->mapWithKeys(fn (string $label, string $key): array => [$key => filled($values[$key] ?? null) ? (string) $values[$key] : self::BLANK])
            ->all();
    }

    /**
     * The PDF, A4, right to left, in the Shabnam font.
     */
    public function pdf(Contract $contract): string
    {
        return PersianPdf::render($contract->contractType->title, self::STYLES.strtr($this->document($contract), self::PDF_REPLACEMENTS));
    }

    public function fileName(Contract $contract): string
    {
        return 'contract-'.($contract->number ?? 'draft-'.$contract->getKey()).'.pdf';
    }

    public static function date(?CarbonInterface $date): ?string
    {
        return $date === null ? null : CalendarUtils::strftime('Y/m/d', $date->copy()->toMutable());
    }

    private static function dateTime(?CarbonInterface $date): string
    {
        return $date === null ? '' : CalendarUtils::strftime('Y/m/d H:i', $date->copy()->toMutable());
    }
}
