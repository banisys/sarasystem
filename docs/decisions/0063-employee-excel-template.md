# 0063 — Excel template and upload for importing employees

- **Status:** accepted
- **Date:** 2026-11-06

## Context
The user asked for a file in «ورود کارمندان از فایل» that a client downloads, with sample data, fills in with their own employees, and where each employee's role is chosen from a select column listing the site's roles. A CSV can't hold a pick list, so the file has to be Excel. Filament's `ImportAction`, used since [ADR 0038](0038-quick-employee-entry.md), reads only CSV, so the filled file also had to be accepted back.

## Options
- **Ask the client to save the Excel file as CSV before uploading:** no upload code, but an extra step that is easy to get wrong (encoding, delimiter) and loses the file's lists.
- **Our own upload, mapping, queue and error report on `maatwebsite/excel`:** repeats what Filament already does.
- **A subclass of `ImportAction` that turns the first sheet into CSV as it is read** (chosen): mapping, queueing and the failed-rows report stay Filament's.

For writing the file, PhpSpreadsheet is used directly: OpenSpout has no data validation, and PhpSpreadsheet is already installed.

## Decision
- The modal asks for the Excel file and nothing else, at the user's request: no CSV, no column mapping, no roles or panel access chosen for the whole file. This replaces those parts of [ADR 0038](0038-quick-employee-entry.md).
- Core [SpreadsheetImportAction](../../app/Filament/Actions/SpreadsheetImportAction.php) overrides `getUploadedFileStream()` and the file rules: only `.xlsx`, up to 5 MB. Columns are matched by title; Filament's mapping fields stay in the form but out of sight, because its action reads the map from them. An unreadable file, or one without a required column, is refused at upload. Formulas are not calculated: their text reaches validation and that row fails. Columns without a title and empty rows are dropped.
- [EmployeeImportTemplate](../../Modules/Employee/app/Support/EmployeeImportTemplate.php) builds the file from `EmployeeImporter::getColumns()`, so the headers match the mapping guesses. Sheets: «کارمندان», «راهنما» and a hidden `lists`. Cells are text, so leading zeros stay.
- «نقش‌ها» is required in every row and lists `EmployeeRoles::options()` for the downloader in the current tenant, the same roles the import will accept from them. «نوع همکاری» is a list too, since it has fixed values.
- The lists warn rather than block, so someone can still type several roles separated by «،»; the import checks every value and reports the row.
- Sample rows use fixed national codes (`EmployeeImporter::SAMPLE_NATIONAL_CODES`). A row with one of them fails with a reason, so a forgotten sample doesn't become an account. A real person with one of these codes can't be imported from a file; quick entry still works for them.

## Consequences
- Files with other column titles can't be imported: the client starts from the template.
- A file can't give panel access; it is given later on the user's page or through «ثبت سریع کارمند».
- Other importers can use `SpreadsheetImportAction` as it is; each writes its own template.
- `phpoffice/phpspreadsheet` is reached only through `pxlrbt/filament-excel`; removing that package would break this, so it should become a direct dependency once approved.
- The role list is a snapshot of the download: a role added later isn't in an old file (typing it still works), and a file passed to a colleague with fewer rights may have rows refused.
- A national code or mobile that arrives as a number (pasted from another sheet) loses its leading zero and the row fails with its reason.
