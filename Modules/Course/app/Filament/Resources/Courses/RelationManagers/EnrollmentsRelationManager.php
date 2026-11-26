<?php

namespace Modules\Course\Filament\Resources\Courses\RelationManagers;

use App\Models\Tenant;
use App\Models\User;
use App\Support\ClassRoster;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\Course\Filament\Resources\Courses\Pages\ManageCourseParticipants;
use Modules\Course\Models\CourseEnrollmentSettings;
use Modules\Course\Support\CourseRoleRoster;

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
            ->headerActions(ActionGroup::make([
                CreateAction::make()
                    ->label(fn (): string => (string) Str::of(CourseEnrollmentSettings::current()->manual_name)
                        ->replace(['ثبت‌نام', 'ثبت نام'], '')->trim()->whenEmpty(fn () => 'دستی'))
                    ->visible(fn (): bool => CourseEnrollmentSettings::current()->manual_enabled)
                    ->before(fn () => abort_unless(CourseEnrollmentSettings::current()->manual_enabled, 403)),
                $this->enrollFromRoleAction(app(CourseRoleRoster::class)),
                ...(app()->bound(ClassRoster::class) ? [$this->enrollFromClassAction(app(ClassRoster::class))] : []),
            ])
                ->label('ثبت‌نام در دوره')
                ->icon(Heroicon::OutlinedUserPlus)
                ->button())
            ->header($this->getPageClass() === ManageCourseParticipants::class
                ? fn (): View => view('filament.admin.course-page-actions', ['actions' => $table->getHeaderActions()])
                : null)
            ->recordActions([EditAction::make()]);
    }

    private function enrollFromClassAction(ClassRoster $rosters): Action
    {
        $canEnroll = fn (): bool => auth()->user() instanceof User
            && $rosters->canBrowse(auth()->user())
            && CourseEnrollmentSettings::current()->manual_enabled;

        return Action::make('enrollFromClass')
            ->label('از کلاس')
            ->modalHeading('ثبت‌نام از کلاس')
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

                $this->enrollUsers($userIds, 'ثبت‌نام از کلاس انجام شد.');
            });
    }

    private function enrollFromRoleAction(CourseRoleRoster $rosters): Action
    {
        $canEnroll = fn (): bool => auth()->user() instanceof User
            && CourseEnrollmentSettings::current()->manual_enabled
            && $rosters->tenantOptions(auth()->user()) !== [];

        return Action::make('enrollFromRole')
            ->label('بر اساس نقش')
            ->modalHeading('ثبت‌نام بر اساس نقش')
            ->icon(Heroicon::OutlinedIdentification)
            ->authorize($canEnroll)
            ->modalDescription('کاربرانی که نقش انتخابی را در محدودهٔ انتخاب‌شده دارند به دوره اضافه می‌شوند. ثبت‌نام‌های قبلی حفظ می‌شوند.')
            ->modalSubmitActionLabel('ثبت‌نام کاربران')
            ->schema([
                Select::make('scope')
                    ->label(config('sarasystem.tenant_label'))->required()->searchable()->live()
                    ->options(fn (): array => $rosters->tenantOptions(auth()->user()))
                    ->afterStateUpdated(fn (Set $set) => $set('role_id', null)),
                Select::make('role_id')
                    ->label('نقش')->required()->searchable()->rule('integer')
                    ->disabled(fn (Get $get): bool => blank($get('scope')))
                    ->options(fn (Get $get): array => $rosters->roleOptions(auth()->user(), $get('scope'))),
            ])
            ->action(function (array $data) use ($rosters, $canEnroll): void {
                abort_unless($canEnroll(), 403);
                $users = $rosters->users(auth()->user(), $data['scope'], (int) $data['role_id']);
                abort_if($users === null, 404);

                $this->enrollUsers($users->select('id')->orderBy('id')->toBase()->cursor()->pluck('id'), 'ثبت‌نام بر اساس نقش انجام شد.');
            });
    }

    /** @param iterable<int> $userIds */
    private function enrollUsers(iterable $userIds, string $title): void
    {
        [$added, $total] = DB::transaction(function () use ($userIds): array {
            $enrollments = $this->course()->enrollments();
            $added = 0;
            $total = 0;

            foreach (LazyCollection::make($userIds)->chunk(500) as $ids) {
                $total += $ids->count();
                $existing = $this->course()->enrollments()->whereIntegerInRaw('user_id', $ids->all())->pluck('user_id');
                foreach ($ids->diff($existing) as $userId) {
                    $added += (int) $enrollments->createOrFirst(['user_id' => $userId])->wasRecentlyCreated;
                }
            }

            return [$added, $total];
        });

        Notification::make()->success()->title($title)
            ->body($added.' ثبت‌نام تازه، '.($total - $added).' ثبت‌نام قبلی.')
            ->send();
    }
}
