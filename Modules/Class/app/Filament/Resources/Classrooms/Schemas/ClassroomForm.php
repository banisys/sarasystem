<?php

namespace Modules\Class\Filament\Resources\Classrooms\Schemas;

use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Builder;
use Modules\Class\Enums\ClassMode;
use Modules\Class\Enums\OnlinePlatform;
use Modules\Class\Models\ClassOnlineRoom;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\OnlineServer;
use Modules\Class\Models\Term;
use Modules\Class\Services\ClassException;
use Modules\Class\Services\Online\OnlineRooms;
use Modules\Class\Support\ClassroomExtensions;

/**
 * A class's details (ADR 0022). Picking a term fills the dates; a
 * sub-class's parent is a top-level class of the same tenant.
 */
class ClassroomForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('عنوان')
                    ->placeholder('مثلاً: هفتم الف، زبان انگلیسی - مقدماتی ۲، دوره‌ی ایمنی کارگاه')
                    ->required()
                    ->maxLength(255),
                TextInput::make('code')
                    ->label('کد')
                    ->maxLength(30)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('tenant_id', filament()->getTenant()?->getKey())),
                Select::make('term_id')
                    ->label('دوره‌ی آموزشی')
                    ->options(fn (?Classroom $record): array => Term::options() + ($record?->term ? [$record->term->getKey() => $record->term->title] : []))
                    ->default(fn (): ?int => array_key_first(Term::options()))
                    ->live()
                    ->afterStateUpdated(function (mixed $state, Set $set): void {
                        $term = Term::definedTerms()->find($state);

                        $set('starts_on', $term?->starts_on?->toDateString());
                        $set('ends_on', $term?->ends_on?->toDateString());
                    }),
                Select::make('parent_id')
                    ->label('زیرکلاسِ')
                    ->helperText('مثلاً گروه آزمایشگاه یک کلاس؛ اعضای آن از کلاس اصلی می‌آیند.')
                    ->relationship('parent', 'title', fn (Builder $query, ?Classroom $record) => $query->topLevel()->when($record, fn (Builder $query) => $query->whereKeyNot($record->getKey())))
                    ->searchable()
                    ->preload()
                    ->disabled(fn (?Classroom $record): bool => $record !== null && $record->children()->exists()),
                ToggleButtons::make('mode')
                    ->label('برگزاری')
                    ->options(ClassMode::class)
                    ->default(ClassMode::InPerson->value)
                    ->inline()
                    ->required()
                    ->live(),
                TextInput::make('capacity')
                    ->label('ظرفیت')
                    ->helperText('تعداد شرکت‌کنندگان؛ خالی یعنی بدون محدودیت.')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(10000),
                TextInput::make('location')
                    ->label('مکان')
                    ->placeholder('مثلاً: ساختمان ۲، کلاس ۱۰۴')
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => self::mode($get)?->meetsInPerson() ?? true),
                // Where it meets online, each platform from a day on (ADR 0073).
                Select::make('online_choice')
                    ->label('بستر کلاس آنلاین')
                    ->helperText('سرورها در «کلاس‌ها ← سرورهای کلاس آنلاین» تعریف می‌شوند.')
                    ->options(fn (?Classroom $record): array => OnlineRooms::choices(self::currentRoom($record)?->choice()))
                    ->default(OnlinePlatform::Link->value)
                    ->selectablePlaceholder(false)
                    ->required()
                    ->live()
                    ->dehydrated(false)
                    ->visible(fn (Get $get): bool => self::mode($get)?->meetsOnline() ?? false)
                    ->loadStateFromRelationshipsUsing(fn (Select $component, ?Classroom $record) => $component->state(self::currentRoom($record)?->choice() ?? OnlinePlatform::Link->value))
                    ->saveRelationshipsUsing(function (Classroom $record, mixed $state, Get $get): void {
                        try {
                            app(OnlineRooms::class)->change(
                                $record,
                                (string) $state,
                                $get('meeting_url'),
                                filled($get('online_from')) ? CarbonImmutable::parse($get('online_from')) : null,
                                auth()->user(),
                                $get('online_rooms_per') === 'session',
                            );
                        } catch (ClassException $exception) {
                            Notification::make()->title($exception->getMessage())->danger()->send();

                            throw new Halt;
                        }
                    }),
                TextInput::make('meeting_url')
                    ->label('لینک کلاس آنلاین')
                    ->placeholder('https://meet.google.com/… یا نشانی Skyroom')
                    ->url()
                    ->rule('url:http,https')
                    ->maxLength(500)
                    ->dehydrated(false)
                    ->visible(fn (Get $get): bool => (self::mode($get)?->meetsOnline() ?? false) && $get('online_choice') === OnlinePlatform::Link->value)
                    ->loadStateFromRelationshipsUsing(fn (TextInput $component, ?Classroom $record) => $component->state(self::currentRoom($record)?->meeting_url)),
                // Adobe Connect: one room for the class or one per session (ADR 0074).
                ToggleButtons::make('online_rooms_per')
                    ->label('اتاق در Adobe Connect')
                    ->options(['class' => 'یک اتاق برای کل کلاس', 'session' => 'یک اتاق برای هر جلسه'])
                    ->helperText(fn (Get $get, ?Classroom $record): string => self::keepsRoom($get, $record)
                        ? 'برای تغییر آن، بستر کلاس را از تاریخی به بستر یا سرور دیگری ببرید.'
                        : 'یک اتاق: نشانی ثابت و فایل‌ها و اسلایدها برای جلسه‌ی بعد می‌ماند. هر جلسه یک اتاق: ضبط و گزارش حضور هر جلسه جدا است.')
                    ->default('class')
                    ->inline()
                    ->required()
                    ->dehydrated(false)
                    ->disabled(fn (Get $get, ?Classroom $record): bool => self::keepsRoom($get, $record))
                    ->visible(fn (Get $get): bool => (self::mode($get)?->meetsOnline() ?? false) && (self::chosenPlatform($get)?->offersRoomPerSession() ?? false))
                    ->loadStateFromRelationshipsUsing(fn (ToggleButtons $component, ?Classroom $record) => $component->state(self::currentRoom($record)?->per_session ? 'session' : 'class')),
                DatePicker::make('online_from')
                    ->label('بستر تازه از تاریخ')
                    ->helperText('جلسه‌های پیش از این روز در بستر قبلی می‌مانند؛ از این روز به بعد در بستر تازه برگزار می‌شوند.')
                    ->jalali()
                    ->default(fn (): string => today()->toDateString())
                    ->required(fn (Get $get, ?Classroom $record): bool => self::switchesPlatform($get, $record))
                    ->dehydrated(false)
                    ->visible(fn (Get $get, ?Classroom $record): bool => self::switchesPlatform($get, $record)),
                DatePicker::make('starts_on')
                    ->label('شروع')
                    ->jalali(),
                DatePicker::make('ends_on')
                    ->label('پایان')
                    ->jalali()
                    ->afterOrEqual('starts_on'),
                Textarea::make('description')
                    ->label('توضیحات')
                    ->rows(2)
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('فعال')
                    ->default(true),
                // Fields other modules add, e.g. the School module's grade (ADR 0031).
                ...ClassroomExtensions::components('form'),
            ])
            ->columns(2);
    }

    /**
     * The room in effect today, or the last one when none has started.
     */
    private static function currentRoom(?Classroom $record): ?ClassOnlineRoom
    {
        if ($record === null) {
            return null;
        }

        return app(OnlineRooms::class)->on($record, today()) ?? $record->onlineRooms->sortBy(fn (ClassOnlineRoom $room) => $room->starts_on?->timestamp ?? 0)->last();
    }

    /**
     * An existing class moving to another platform: ask from when.
     */
    private static function switchesPlatform(Get $get, ?Classroom $record): bool
    {
        $current = self::currentRoom($record);

        return $current !== null && (self::mode($get)?->meetsOnline() ?? false) && $get('online_choice') !== $current->choice();
    }

    /**
     * The class stays in the room it has: its way (per class or per
     * session) can't change there.
     */
    private static function keepsRoom(Get $get, ?Classroom $record): bool
    {
        $current = self::currentRoom($record);

        return $current !== null && $get('online_choice') === $current->choice();
    }

    /**
     * The platform of the server picked in «بستر کلاس آنلاین».
     */
    private static function chosenPlatform(Get $get): ?OnlinePlatform
    {
        $choice = (string) $get('online_choice');

        return str_starts_with($choice, 'server:') ? OnlineServer::query()->find((int) substr($choice, 7))?->platform : null;
    }

    private static function mode(Get $get): ?ClassMode
    {
        $mode = $get('mode');

        return $mode instanceof ClassMode ? $mode : ClassMode::tryFrom((string) $mode);
    }
}
