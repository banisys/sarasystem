<?php

namespace Modules\School\Filament\Resources\Enrollments\Tables;

use App\Support\Identifiers;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\Term;
use Modules\School\Filament\Resources\Enrollments\Schemas\EnrollmentForm;
use Modules\School\Models\Enrollment;
use Modules\School\Models\Grade;
use Modules\School\Models\Major;
use Modules\School\Models\ScorePeriod;
use Modules\School\Services\Enrollments;
use Modules\School\Services\ReportCards;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EnrollmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'term', 'grade.stage', 'major', 'classroom']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('user.name')->label('نام'),
                TextColumn::make('user.national_code')->label('کد ملی')->copyable(),
                TextColumn::make('term.title')->label('سال تحصیلی'),
                TextColumn::make('grade.title')->label('پایه')->description(fn (Enrollment $record): ?string => $record->grade?->stage?->title),
                TextColumn::make('major.title')->label('رشته')->placeholder('—'),
                TextColumn::make('classroom.title')->label('کلاس')->placeholder('—'),
            ])
            ->filters([
                Filter::make('person')
                    ->schema([TextInput::make('value')->label('نام یا کد ملی')])
                    ->query(function (Builder $query, array $data): void {
                        $value = Identifiers::toLatinDigits(trim((string) ($data['value'] ?? '')));

                        if ($value !== '') {
                            $query->whereHas('user', fn (Builder $user) => $user->whereLike('name', "%{$value}%")->orWhereLike('national_code', "%{$value}%"));
                        }
                    }),
                SelectFilter::make('term_id')->label('سال تحصیلی')->options(fn (): array => Term::definedTerms()->orderByDesc('starts_on')->pluck('title', 'id')->all()),
                SelectFilter::make('grade_id')->label('پایه')->options(fn (): array => Grade::options()),
                SelectFilter::make('major_id')->label('رشته')->options(fn (): array => Major::options()),
                SelectFilter::make('classroom_id')->label('کلاس')->options(fn (): array => Classroom::query()->topLevel()->orderBy('title')->pluck('title', 'id')->all())->searchable(),
            ])
            ->recordActions([
                Action::make('reportCard')
                    ->label('کارنامه (PDF)')
                    ->icon('heroicon-o-document-arrow-down')
                    ->authorize('view')
                    ->modalDescription('کارنامه از نمره‌های همین حالا ساخته می‌شود، چه منتشر شده باشد چه نه.')
                    ->schema([
                        Select::make('period_id')
                            ->label('دوره‌ی نمره‌دهی')
                            ->options(fn (Enrollment $record): array => ScorePeriod::definedPeriods()->where('term_id', $record->term_id)->pluck('title', 'id')->all())
                            ->required(),
                    ])
                    ->action(function (array $data, Enrollment $record, ReportCards $cards): StreamedResponse {
                        $card = $cards->build($record, ScorePeriod::definedPeriods()->findOrFail($data['period_id']));
                        $pdf = $cards->pdf($card);

                        return response()->streamDownload(fn () => print ($pdf), $cards->fileName($card), ['Content-Type' => 'application/pdf']);
                    }),
                EditAction::make()
                    ->using(fn (Model $record, array $data): Enrollment => EnrollmentForm::save($record, $data)),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkAction::make('promote')
                    ->label('ارتقا به سال بعد')
                    ->icon('heroicon-o-arrow-trending-up')
                    ->authorize('create', Enrollment::class)
                    ->modalDescription('برای هر پرونده‌ی انتخاب‌شده، پرونده‌ای در سال انتخاب‌شده با پایه‌ی بالاتر (و همان رشته) ساخته می‌شود؛ کلاس بعداً انتخاب می‌شود. پایه‌ی آخر و کسانی که در آن سال پرونده دارند کنار می‌مانند.')
                    ->schema([
                        Select::make('term_id')->label('سال تحصیلی بعد')->options(fn (): array => Term::options())->required(),
                    ])
                    ->action(function (Collection $records, array $data, Enrollments $enrollments): void {
                        $result = $enrollments->promote($records, Term::definedTerms()->findOrFail($data['term_id']));

                        Notification::make()
                            ->title("ارتقا: {$result['promoted']} نفر؛ دانش‌آموخته: {$result['graduated']}؛ از پیش پرونده داشتند: {$result['skipped']}")
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }
}
