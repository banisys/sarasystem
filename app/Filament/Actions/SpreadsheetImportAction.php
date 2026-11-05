<?php

namespace App\Filament\Actions;

use Closure;
use Filament\Actions\ImportAction;
use Filament\Actions\Imports\ImportColumn;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Fieldset;
use Illuminate\Support\Str;
use League\Csv\Reader;
use League\Csv\Writer;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use SplTempFileObject;
use Throwable;

/**
 * Filament's import, for Excel files only (ADR 0063): the first sheet of an
 * .xlsx is turned into CSV as it is read, so the queue and the report of failed
 * rows stay Filament's. Columns are matched by their titles, without asking:
 * a file lacking a required column is refused at upload.
 *
 * Formulas are not calculated: their text reaches the importer's validation.
 */
class SpreadsheetImportAction extends ImportAction
{
    private const MAX_FILE_KILOBYTES = 5120;

    /**
     * The CSV made from each Excel file in this request, by path (null when
     * unreadable): Filament reads the upload several times per request.
     *
     * @var array<string, string|null>
     */
    private array $convertedFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $parentSchema = $this->schema;

        $this->schema(function (ImportAction $action) use ($parentSchema): array {
            $components = $action->evaluate($parentSchema);

            foreach ($components as $component) {
                if ($component instanceof FileUpload) {
                    $component
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            // How some systems detect an .xlsx; the extension rule and the reader still apply.
                            'application/zip',
                            'application/octet-stream',
                        ])
                        ->maxSize(self::MAX_FILE_KILOBYTES)
                        // The theme hides FilePond's list of MIME types when a file is refused.
                        ->extraAttributes(['class' => 'fi-spreadsheet-upload'])
                        ->placeholder('فایل Excel را اینجا بکشید و رها کنید یا انتخاب کنید');
                }

                // Only out of sight: the parent's action reads the map from this state.
                if ($component instanceof Fieldset) {
                    $component->extraAttributes(['style' => 'display: none']);
                }
            }

            return $components;
        });
    }

    /**
     * @return array<mixed>
     */
    public function getFileValidationRules(): array
    {
        $rules = array_values(array_filter(parent::getFileValidationRules(), fn (mixed $rule): bool => $rule !== 'extensions:csv,txt'));

        return [
            'extensions:xlsx',
            fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                if (! $this->isSpreadsheet($value)) {
                    return;
                }

                $csv = $this->spreadsheetAsCsv($value);

                if ($csv === null) {
                    $fail('این فایل Excel خوانده نشد یا ردیف عنوان ستون‌ها را ندارد.');

                    return;
                }

                if (($missing = $this->missingColumns($csv)) !== []) {
                    $fail('این ستون‌ها در فایل نیست: «'.implode('»، «', $missing).'». از فایل نمونه استفاده کنید.');
                }
            },
            ...$rules,
        ];
    }

    /**
     * @return resource|false
     */
    public function getUploadedFileStream(TemporaryUploadedFile $file)
    {
        $csv = $this->isSpreadsheet($file) ? $this->spreadsheetAsCsv($file) : null;

        if ($csv === null) {
            return false;
        }

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $csv);
        rewind($stream);

        return $stream;
    }

    /**
     * Labels of the importer's required columns that no title in the file matches.
     *
     * @return list<string>
     */
    private function missingColumns(string $csv): array
    {
        $titles = array_map(Str::lower(...), Reader::fromString($csv)->nth(0));

        return collect($this->getImporter()::getColumns())
            ->filter(fn (ImportColumn $column): bool => $column->isMappingRequired() && array_intersect($titles, $column->getGuesses()) === [])
            ->map(fn (ImportColumn $column): string => (string) $column->getLabel())
            ->values()
            ->all();
    }

    private function isSpreadsheet(TemporaryUploadedFile $file): bool
    {
        return strtolower($file->getClientOriginalExtension()) === 'xlsx';
    }

    private function spreadsheetAsCsv(TemporaryUploadedFile $file): ?string
    {
        return $this->convertedFiles[$file->getRealPath()] ??= $this->convert($file);
    }

    private function convert(TemporaryUploadedFile $file): ?string
    {
        $path = tempnam(sys_get_temp_dir(), 'import');

        try {
            file_put_contents($path, $file->get());

            /** @var Xlsx $reader */
            $reader = IOFactory::createReader(IOFactory::READER_XLSX);
            $reader->setReadDataOnly(true);
            $reader->setLoadSheetsOnly([$reader->listWorksheetNames($path)[0]]);

            $rows = $reader->load($path)->getSheet(0)->toArray(null, false, false, false);
        } catch (ReaderException|Throwable) {
            return null;
        } finally {
            unlink($path);
        }

        return $this->rowsToCsv($rows);
    }

    /**
     * Cells to text, without columns that have no title (they would count as
     * duplicate titles) and without empty rows (a sheet often ends in many).
     *
     * @param  list<list<mixed>>  $rows
     */
    private function rowsToCsv(array $rows): ?string
    {
        $rows = array_map(fn (array $row): array => array_map(fn (mixed $cell): string => trim((string) $cell), $row), $rows);
        $titles = array_filter(array_shift($rows) ?? [], fn (string $title): bool => $title !== '');

        if ($titles === []) {
            return null;
        }

        $csv = Writer::from(new SplTempFileObject);
        $csv->insertOne($titles);

        foreach ($rows as $row) {
            $cells = array_intersect_key($row, $titles);

            if (array_filter($cells, fn (string $cell): bool => $cell !== '') !== []) {
                $csv->insertOne($cells);
            }
        }

        return $csv->toString();
    }
}
