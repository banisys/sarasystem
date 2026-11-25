<?php

namespace Modules\Course\Filament\Resources\Courses\RelationManagers;

use App\Models\Tenant;
use App\Models\User;
use App\Support\ClassRoster;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Course\Models\CourseEnrollmentSettings;

class EnrollmentsRelationManager extends CoursePeopleRelationManager
{
    protected static string $relationship = 'enrollments';

    protected static ?string $title = 'ثبت‌نام‌ها';

    protected static ?string $modelLabel = 'ثبت‌نام';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->userSelect(config('sarasystem.learner_label'))->hiddenOn('edit'),
            DateTimePicker::make('starts_at')->label('شروع دسترسی')->jalali()->seconds(false)
                ->helperText('خالی: از همین حالا'),
            DateTimePicker::make('expires_at')->label('پایان دسترسی')->jalali()->seconds(false)
                ->after(fn (Get $get): ?string => filled($get('starts_at')) ? 'starts_at' : null)
                ->helperText('خالی: بدون تاریخ پایان'),
            Toggle::make('is_revoked')->label('لغو دسترسی'),
        ]);
    }

    public function table(Table $table): Table
    {
        $inCourse = fn (Builder $lessons): Builder => $lessons->where('course_id', $this->course()->getKey());

        return $table->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount([
            'completions' => fn (Builder $completed): Builder => $completed->whereHas('lesson', $inCourse),
        ]))->columns([
            TextColumn::make('user.name')->label(config('sarasystem.learner_label'))->searchable(),
            TextColumn::make('starts_at')->label('شروع دسترسی')->jalaliDateTime(),
            TextColumn::make('expires_at')->label('پایان دسترسی')->jalaliDateTime(),
            TextColumn::make('completions_count')->label('درس‌های تکمیل‌شده')->sortable(),
            IconColumn::make('is_revoked')->label('لغوشده')->boolean(),
        ])
            ->pluralModelLabel('ثبت‌نام‌ها')
            ->headerActions([
                CreateAction::make()
                    ->label(fn (): string => CourseEnrollmentSettings::current()->manual_name)
                    ->visible(fn (): bool => CourseEnrollmentSettings::current()->manual_enabled)
                    ->before(fn () => abort_unless(CourseEnrollmentSettings::current()->manual_enabled, 403)),
                ...(app()->bound(ClassRoster::class) ? [$this->enrollFromClassAction(app(ClassRoster::class))] : []),
            ])
            ->recordActions([EditAction::make()]);
    }

    private function enrollFromClassAction(ClassRoster $rosters): Action
    {
        $canEnroll = fn (): bool => auth()->user() instanceof User
            && $rosters->canBrowse(auth()->user())
            && CourseEnrollmentSettings::current()->manual_enabled;

        return Action::make('enrollFromClass')
            ->label('ثبت‌نام از کلاس')
            ->icon(Heroicon::OutlinedUserGroup)
            ->authorize($canEnroll)
            ->modalDescription(fn (): string => 'فراگیران فعلی کلاس در '.Tenant::current()?->name.' به دوره اضافه می‌شوند. ثبت‌نام‌های قبلی حفظ می‌شوند.')
            ->modalSubmitActionLabel('ثبت‌نام فراگیران')
            ->schema([
                Select::make('class_id')
                    ->label('کلاس')->required()->searchable()->rule('integer')
                    ->options(fn (): array => $rosters->options(auth()->user()))
                    ->getSearchResultsUsing(fn (string $search): array => $rosters->options(auth()->user(), $search))
                    ->getOptionLabelUsing(function (mixed $value) use ($rosters): ?string {
                        $classId = filter_var($value, FILTER_VALIDATE_INT);

                        return $classId === false ? null : $rosters->title(auth()->user(), $classId);
                    }),
            ])
            ->action(function (array $data) use ($rosters, $canEnroll): void {
                abort_unless($canEnroll(), 403);
                $userIds = $rosters->attendeeIds(auth()->user(), (int) $data['class_id']);
                abort_if($userIds === null, 404);

                $added = DB::transaction(function () use ($userIds): int {
                    $enrollments = $this->course()->enrollments();
                    $added = 0;

                    foreach ($userIds as $userId) {
                        $added += (int) $enrollments->firstOrCreate(['user_id' => $userId])->wasRecentlyCreated;
                    }

                    return $added;
                });

                Notification::make()->success()->title('ثبت‌نام از کلاس انجام شد.')
                    ->body($added.' ثبت‌نام تازه، '.(count($userIds) - $added).' ثبت‌نام قبلی.')
                    ->send();
            });
    }
}
