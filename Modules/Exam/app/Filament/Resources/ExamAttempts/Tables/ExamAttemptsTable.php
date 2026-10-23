<?php

namespace Modules\Exam\Filament\Resources\ExamAttempts\Tables;

use App\Filament\Support\TextFilter;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Exam\Enums\AttemptStatus;
use Modules\Exam\Models\ExamAttempt;

class ExamAttemptsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['exam', 'user']))
            ->defaultSort('started_at', 'desc')
            ->columns([
                TextColumn::make('user.name')
                    ->label('شرکت‌کننده'),
                TextColumn::make('user.mobile')
                    ->label('موبایل')
                    ->toggledHiddenByDefault(),
                TextColumn::make('exam.title')
                    ->label('آزمون'),
                TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge(),
                TextColumn::make('score')
                    ->label('نمره')
                    ->state(fn (ExamAttempt $record): ?string => $record->score === null ? null : "{$record->score} از {$record->exam->total_score}")
                    ->placeholder('—'),
                IconColumn::make('passed')
                    ->label('قبول')
                    ->state(fn (ExamAttempt $record): ?bool => $record->passed())
                    ->boolean(),
                TextColumn::make('started_at')
                    ->label('شروع')
                    ->jalaliDateTime()
                    ->sortable(),
                TextColumn::make('submitted_at')
                    ->label('پایان')
                    ->jalaliDateTime()
                    ->description(fn (ExamAttempt $record): ?string => $record->timed_out ? 'با پایان وقت' : null)
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('exam')
                    ->label('آزمون')
                    ->relationship('exam', 'title'),
                SelectFilter::make('status')
                    ->label('وضعیت')
                    ->options(AttemptStatus::class),
                TextFilter::make('user')
                    ->label('شرکت‌کننده')
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereHas('user', fn (Builder $users) => $users->whereLike('name', '%'.addcslashes(trim($data['value']), '%_\\').'%'))
                        : $query),
            ])
            ->recordActions([
                EditAction::make()
                    ->label(fn (ExamAttempt $record): string => $record->status === AttemptStatus::PendingReview ? 'تصحیح' : 'پاسخ‌ها')
                    ->icon(Heroicon::OutlinedPencilSquare),
                DeleteAction::make()
                    ->modalDescription('این شرکت و پاسخ‌هایش حذف می‌شود و یک بار از دفعات شرکت کاربر کم می‌شود.'),
            ]);
    }
}
