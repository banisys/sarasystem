<?php

namespace Modules\School\Filament\Resources\ScorePeriods;

use App\Filament\Concerns\OnlyInPrimaryTenant;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Class\Models\Term;
use Modules\School\Filament\Resources\ScorePeriods\Pages\ListScorePeriods;
use Modules\School\Models\ScorePeriod;
use UnitEnum;

/**
 * The score periods of each academic year («مهر»، «نوبت اول»، …), defined
 * in the primary tenant (ADR 0032): what they cover, until when teachers
 * enter scores, and whether their report cards are published.
 */
class ScorePeriodResource extends Resource
{
    use OnlyInPrimaryTenant;

    protected static ?string $model = ScorePeriod::class;

    // See StageResource.
    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    protected static string|UnitEnum|null $navigationGroup = 'مدرسه';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'school-score-periods';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $modelLabel = 'دوره‌ی نمره‌دهی';

    protected static ?string $pluralModelLabel = 'دوره‌های نمره‌دهی';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('term_id')
                    ->label('سال تحصیلی')
                    ->options(fn (?ScorePeriod $record): array => Term::options() + ($record?->term ? [$record->term->getKey() => $record->term->title] : []))
                    ->default(fn (): ?int => array_key_first(Term::options()))
                    ->required(),
                TextInput::make('title')->label('عنوان')->placeholder('مثلاً: مهر، آبان، نوبت اول، نوبت دوم')->required()->maxLength(255),
                DatePicker::make('starts_on')->label('از')->helperText('غیبت‌ها و تکالیف این بازه در کارنامه می‌آید.')->jalali()->required(),
                DatePicker::make('ends_on')->label('تا')->jalali()->afterOrEqual('starts_on')->required(),
                DatePicker::make('entry_closes_on')->label('مهلت ثبت نمره')->helperText('معلم‌ها تا پایان این روز نمره ثبت می‌کنند؛ خالی یعنی تا انتشار.')->jalali(),
                Toggle::make('is_published')->label('کارنامه منتشر شده است')->helperText('دانش‌آموز و اولیا کارنامه را می‌بینند و ثبت نمره برای معلم‌ها بسته می‌شود.'),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('term')->withCount('scores'))
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                TextColumn::make('title')->label('عنوان'),
                TextColumn::make('term.title')->label('سال تحصیلی'),
                TextColumn::make('starts_on')->label('از')->jalaliDate(),
                TextColumn::make('ends_on')->label('تا')->jalaliDate(),
                TextColumn::make('entry_closes_on')->label('مهلت ثبت نمره')->jalaliDate()->placeholder('تا انتشار'),
                IconColumn::make('is_published')->label('منتشر شده')->boolean(),
                TextColumn::make('scores_count')->label('نمره‌ها'),
            ])
            ->filters([
                SelectFilter::make('term_id')->label('سال تحصیلی')->options(fn (): array => Term::definedTerms()->orderByDesc('starts_on')->pluck('title', 'id')->all()),
            ])
            ->recordActions([
                Action::make('publish')
                    ->label(fn (ScorePeriod $record): string => $record->is_published ? 'لغو انتشار کارنامه' : 'انتشار کارنامه')
                    ->icon(Heroicon::OutlinedMegaphone)
                    ->requiresConfirmation()
                    ->authorize('update')
                    ->action(fn (ScorePeriod $record) => $record->update(['is_published' => ! $record->is_published])),
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (ScorePeriod $record): bool => $record->scores_count === 0)
                    ->modalDescription('دوره‌ای که نمره دارد حذف نمی‌شود.'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListScorePeriods::route('/'),
        ];
    }
}
