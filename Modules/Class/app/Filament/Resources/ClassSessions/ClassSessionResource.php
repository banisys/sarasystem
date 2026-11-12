<?php

namespace Modules\Class\Filament\Resources\ClassSessions;

use App\Models\Tenant;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Class\Enums\SessionKind;
use Modules\Class\Filament\Actions\SessionActions;
use Modules\Class\Filament\Resources\ClassSessions\Pages\LearnerSessions;
use Modules\Class\Filament\Resources\ClassSessions\Pages\ListClassSessions;
use Modules\Class\Filament\Resources\ClassSessions\Pages\TeacherSessions;
use Modules\Class\Filament\Resources\ClassSessions\Pages\ViewClassSession;
use Modules\Class\Filament\Resources\ClassSessions\Pages\ViewExamSession;
use Modules\Class\Filament\Resources\ClassSessions\Tables\ClassSessionsTable;
use Modules\Class\Models\ClassSession;
use UnitEnum;

/**
 * Sessions of every class of the current tenant (ADR 0023), from today on
 * by default; the same row actions as a class's «جلسه‌ها» tab. A teacher's
 * or a learner's sessions on a calendar: PersonSessions (ADR 0048). One
 * session's page: ViewClassSession (ADR 0050).
 */
class ClassSessionResource extends Resource
{
    protected static ?string $model = ClassSession::class;

    // See TermResource.
    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'کلاس‌ها';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'class-sessions';

    protected static ?string $modelLabel = 'جلسه';

    protected static ?string $pluralModelLabel = 'جلسه‌ها';

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Whoever reaches a calendar that links here: the tenant's sessions, or
     * a class's «جلسه‌ها» tab, which needs only the right to see the class.
     */
    public static function canView(Model $record): bool
    {
        $user = auth()->user();

        /** @var ClassSession $record */
        return $user !== null
            && ($user->can('viewAny', ClassSession::class) || $user->can('view', $record) || ($record->classroom !== null && $user->can('view', $record->classroom)));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components(SessionActions::detailsEntries())->columns(1);
    }

    public static function detailsUrl(ClassSession $session, ?Tenant $tenant = null): string
    {
        return static::getUrl($session->kind === SessionKind::Exam ? 'view-exam' : 'view', ['record' => $session], tenant: $tenant);
    }

    public static function table(Table $table): Table
    {
        return ClassSessionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClassSessions::route('/'),
            'teacher' => TeacherSessions::route('/teachers/{user}'),
            'learner' => LearnerSessions::route('/learners/{user}'),
            'view-exam' => ViewExamSession::route('/exams/{record}'),
            'view' => ViewClassSession::route('/{record}'),
        ];
    }
}
