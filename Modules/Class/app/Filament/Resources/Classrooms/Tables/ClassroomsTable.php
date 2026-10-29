<?php

namespace Modules\Class\Filament\Resources\Classrooms\Tables;

use App\Filament\Support\TextFilter;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Class\Enums\ClassMode;
use Modules\Class\Models\ClassMember;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\Term;

class ClassroomsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['term', 'parent', 'members' => fn ($members) => $members->current()->with(['user', 'classRole'])])
                ->withCount(['children', 'members']))
            ->defaultSort('title')
            ->columns([
                TextColumn::make('title')
                    ->label('عنوان')
                    ->description(fn (Classroom $record): ?string => $record->parent ? 'زیرکلاسِ '.$record->parent->title : null)
                    ->sortable(),
                TextColumn::make('code')
                    ->label('کد')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('term.title')
                    ->label('دوره')
                    ->placeholder('—'),
                TextColumn::make('mode')
                    ->label('برگزاری')
                    ->badge(),
                TextColumn::make('staff')
                    ->label('کادر')
                    ->state(fn (Classroom $record): array => $record->members
                        ->reject(fn (ClassMember $member): bool => $member->classRole->is_attendee)
                        ->map(fn (ClassMember $member): string => $member->user->name.' ('.$member->classRole->title.')')
                        ->values()->all())
                    ->listWithLineBreaks()
                    ->placeholder('—'),
                TextColumn::make('attendees')
                    ->label('شرکت‌کنندگان')
                    ->state(fn (Classroom $record): string => $record->members->filter(fn (ClassMember $member): bool => $member->classRole->is_attendee)->count()
                        .($record->capacity !== null ? ' / '.$record->capacity : '')),
                IconColumn::make('is_active')
                    ->label('فعال')
                    ->boolean(),
            ])
            ->filters([
                TextFilter::make('title')->label('عنوان'),
                SelectFilter::make('term_id')
                    ->label('دوره')
                    ->options(fn (): array => Term::definedTerms()->orderByDesc('starts_on')->pluck('title', 'id')->all()),
                SelectFilter::make('mode')
                    ->label('برگزاری')
                    ->options(ClassMode::class),
                TernaryFilter::make('is_active')->label('فعال'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->modalWidth('3xl'),
                DeleteAction::make()
                    ->visible(fn (Classroom $record): bool => $record->members_count === 0 && $record->children_count === 0)
                    ->modalDescription('کلاسی که عضو یا زیرکلاس دارد حذف نمی‌شود؛ آن را غیرفعال کنید.'),
            ]);
    }
}
