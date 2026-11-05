<?php

namespace Modules\Employee\Support;

use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Imports\ImportColumn;
use Modules\Employee\Enums\EmploymentType;
use Modules\Employee\Filament\Imports\EmployeeImporter;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The Excel file a client fills in for «ورود از فایل» (ADR 0063): the
 * importer's columns, sample rows, and pick lists for the roles the downloader
 * may give in this tenant and for the employment types.
 */
class EmployeeImportTemplate
{
    public const SHEET_EMPLOYEES = 'کارمندان';

    public const SHEET_GUIDE = 'راهنما';

    public const SHEET_LISTS = 'lists';

    /** Rows of the first sheet that offer the pick lists. */
    public const LAST_ROW = 2000;

    public static function download(User $actor, Tenant $tenant): StreamedResponse
    {
        $spreadsheet = static::spreadsheet($actor, $tenant);

        return response()->streamDownload(
            fn () => (new Xlsx($spreadsheet))->save('php://output'),
            "employees-{$tenant->slug}.xlsx",
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    public static function spreadsheet(User $actor, Tenant $tenant): Spreadsheet
    {
        $roles = array_values(EmployeeRoles::options($actor, $tenant));
        $employmentTypes = array_map(fn (EmploymentType $type): string => $type->getLabel(), EmploymentType::cases());

        $spreadsheet = new Spreadsheet;
        $employees = $spreadsheet->getActiveSheet()->setTitle(self::SHEET_EMPLOYEES)->setRightToLeft(true);
        $columns = EmployeeImporter::getColumns();

        static::writeHeader($employees, $columns);
        static::writeSamples($employees, $columns, $roles, $employmentTypes);

        $lists = $spreadsheet->createSheet()->setTitle(self::SHEET_LISTS);
        $lists->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

        static::offerList($employees, $lists, $columns, 'roles', 'A', $roles, 'نقش', 'یک نقش را از فهرست انتخاب کنید. برای چند نقش، نام‌ها را با «،» جدا بنویسید.');
        static::offerList($employees, $lists, $columns, 'employment_type', 'B', $employmentTypes, 'نوع همکاری', 'نوع همکاری را از فهرست انتخاب کنید.');

        static::writeGuide($spreadsheet->createSheet(1)->setTitle(self::SHEET_GUIDE)->setRightToLeft(true));

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * @param  list<ImportColumn>  $columns
     */
    private static function writeHeader(Worksheet $sheet, array $columns): void
    {
        foreach ($columns as $index => $column) {
            $letter = Coordinate::stringFromColumnIndex($index + 1);

            $sheet->setCellValueExplicit("{$letter}1", (string) $column->getLabel(), DataType::TYPE_STRING);
            $sheet->getColumnDimension($letter)->setWidth(22);
            // Text, so Excel keeps the leading zero of a national code or mobile.
            $sheet->getStyle("{$letter}:{$letter}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        }

        $header = $sheet->getStyle('A1:'.Coordinate::stringFromColumnIndex(count($columns)).'1');
        $header->getFont()->setBold(true);
        $header->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E5E7EB');
        $sheet->freezePane('A2');
    }

    /**
     * @param  list<ImportColumn>  $columns
     * @param  list<string>  $roles
     * @param  list<string>  $employmentTypes
     */
    private static function writeSamples(Worksheet $sheet, array $columns, array $roles, array $employmentTypes): void
    {
        $samples = [
            ['name' => 'زهرا احمدی', 'mobile' => '09121234567', 'job_title' => 'معلم ریاضی', 'personnel_code' => '1024', 'hired_at' => '1404/07/01'],
            ['name' => 'رضا کریمی', 'mobile' => '09121234568', 'job_title' => 'کارشناس آموزش', 'personnel_code' => '1025', 'hired_at' => '1404/08/15'],
        ];

        foreach ($samples as $index => $sample) {
            $row = $index + 2;
            $sample += [
                'national_code' => EmployeeImporter::SAMPLE_NATIONAL_CODES[$index],
                'employment_type' => $employmentTypes[$index] ?? $employmentTypes[0],
                'roles' => $roles[$index] ?? $roles[0] ?? '',
            ];

            foreach ($columns as $columnIndex => $column) {
                $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($columnIndex + 1).$row, $sample[$column->getName()] ?? '', DataType::TYPE_STRING);
            }
        }

        $sheet->getStyle('A2:'.Coordinate::stringFromColumnIndex(count($columns)).(count($samples) + 1))
            ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF3C7');
    }

    /**
     * A pick list on one column, its items in the hidden sheet: a list written
     * in the rule itself is limited to 255 characters. Excel warns about other
     * values but allows them, so several roles can be typed; the import checks.
     *
     * @param  list<ImportColumn>  $columns
     * @param  list<string>  $items
     */
    private static function offerList(Worksheet $sheet, Worksheet $lists, array $columns, string $columnName, string $listColumn, array $items, string $title, string $prompt): void
    {
        if ($items === []) {
            return;
        }

        foreach ($items as $index => $item) {
            $lists->setCellValueExplicit($listColumn.($index + 1), $item, DataType::TYPE_STRING);
        }

        $position = array_search($columnName, array_map(fn (ImportColumn $column): string => $column->getName(), $columns), true);
        $letter = Coordinate::stringFromColumnIndex($position + 1);

        $validation = (new DataValidation)
            ->setType(DataValidation::TYPE_LIST)
            ->setErrorStyle(DataValidation::STYLE_WARNING)
            ->setAllowBlank(true)
            ->setShowDropDown(true)
            ->setShowInputMessage(true)
            ->setShowErrorMessage(true)
            ->setPromptTitle($title)
            ->setPrompt($prompt)
            ->setErrorTitle($title)
            ->setError('این مقدار در فهرست نیست و ممکن است در ورود فایل رد شود.')
            ->setFormula1(self::SHEET_LISTS.'!$'.$listColumn.'$1:$'.$listColumn.'$'.count($items));

        $sheet->setDataValidation("{$letter}2:{$letter}".self::LAST_ROW, $validation);
    }

    private static function writeGuide(Worksheet $sheet): void
    {
        $lines = [
            'راهنمای پر کردن فایل',
            'در برگه‌ی «'.self::SHEET_EMPLOYEES.'» هر ردیف یک کارمند است. عنوان ستون‌ها را تغییر ندهید.',
            'ردیف‌های زرد فقط نمونه‌اند: پیش از بارگذاری آن‌ها را پاک کنید یا با اطلاعات کارمندان خود جایگزین کنید.',
            'نام و نام خانوادگی، کد ملی، موبایل، سمت و نقش لازم‌اند؛ ستون‌های دیگر را می‌توانید خالی بگذارید.',
            'نقش و نوع همکاری را از فهرست هر خانه انتخاب کنید. فهرست نقش‌ها همان نقش‌هایی است که هنگام دریافت فایل می‌توانستید بدهید.',
            'تاریخ شروع به کار را شمسی بنویسید؛ مثلاً 1404/07/01.',
        ];

        foreach ($lines as $index => $line) {
            $sheet->setCellValueExplicit('A'.($index + 1), $line, DataType::TYPE_STRING);
        }

        $sheet->getStyle('A1')->getFont()->setBold(true);
        $sheet->getColumnDimension('A')->setWidth(110);
    }
}
