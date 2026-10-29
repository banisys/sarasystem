<?php

namespace App\Support;

use Mpdf\Mpdf;

/**
 * Right-to-left A4 PDFs in the Shabnam font (resources/fonts/*.ttf), with
 * mPDF: contracts (ADR 0020), report cards (ADR 0032).
 */
class PersianPdf
{
    /**
     * @param  array<string, mixed>  $options  mPDF options over the defaults, e.g. margins
     */
    public static function render(string $title, string $html, array $options = []): string
    {
        $directory = storage_path('app/mpdf');
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $directory,
            'fontDir' => [resource_path('fonts')],
            'fontdata' => [
                'shabnam' => ['R' => 'Shabnam.ttf', 'B' => 'Shabnam-Bold.ttf', 'useOTL' => 0xFF, 'useKashida' => 75],
            ],
            'default_font' => 'shabnam',
            'directionality' => 'rtl',
            'margin_top' => 15,
            'margin_bottom' => 15,
            ...$options,
        ]);
        $mpdf->SetTitle($title);
        $mpdf->SetDirectionality('rtl');
        $mpdf->WriteHTML('<div dir="rtl">'.$html.'</div>');

        return $mpdf->Output('', 'S');
    }
}
