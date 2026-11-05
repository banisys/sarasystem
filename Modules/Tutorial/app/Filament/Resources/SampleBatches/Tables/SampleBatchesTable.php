<?php

namespace Modules\Tutorial\Filament\Resources\SampleBatches\Tables;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Modules\Class\Filament\Resources\Classrooms\ClassroomResource;
use Modules\Tutorial\Models\SampleBatch;
use Modules\Tutorial\Services\SampleBatches;
use RuntimeException;

class SampleBatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['tenant', 'user'])->withCount('records'))
            ->emptyStateHeading('هنوز داده‌ی نمونه‌ای ساخته نشده است')
            ->emptyStateDescription('با «ساخت داده‌ی نمونه» کلاسی با اعضا، برنامه و سابقه‌ی جلسه‌ها بسازید.')
            ->columns([
                TextColumn::make('code')
                    ->label('کد')
                    ->badge()
                    ->copyable(),
                TextColumn::make('title')
                    ->label('کلاس'),
                TextColumn::make('tenant.name')
                    ->label(config('sarasystem.tenant_label'))
                    ->placeholder('—'),
                TextColumn::make('records_count')
                    ->label('رکوردها')
                    ->numeric(),
                TextColumn::make('user.name')
                    ->label('سازنده')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('ساخته‌شده')
                    ->jalaliDateTime()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('classroom')
                    ->label(fn (SampleBatch $record): string => (int) ($record->options['classes'] ?? 1) > 1 ? 'مشاهده‌ی کلاس‌ها' : 'مشاهده‌ی کلاس')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(function (SampleBatch $record, SampleBatches $batches): ?string {
                        $classrooms = $batches->classrooms($record);
                        $tenant = $batches->tenant($record);

                        return match (true) {
                            $classrooms->isEmpty() || $tenant === null => null,
                            $classrooms->count() === 1 => ClassroomResource::getUrl('members', ['record' => $classrooms->first()], panel: 'admin', tenant: $tenant),
                            // The classes list, filtered to the batch's titles.
                            default => ClassroomResource::getUrl('index', ['tableFilters' => ['title' => ['value' => $record->title]]], panel: 'admin', tenant: $tenant),
                        };
                    }),
                Action::make('summary')
                    ->label('آنچه ساخته شد')
                    ->icon(Heroicon::OutlinedListBullet)
                    ->modalHeading(fn (SampleBatch $record): string => "{$record->title} ({$record->code})")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('بستن')
                    ->modalContent(fn (SampleBatch $record, SampleBatches $batches): HtmlString => new HtmlString(
                        '<ul class="grid gap-1 text-sm sm:grid-cols-2">'
                        .collect($batches->summary($record))->map(fn (int $rows, string $label): string => '<li class="flex justify-between gap-4 rounded-lg bg-gray-50 px-3 py-2 dark:bg-white/5"><span>'.e($label).'</span><span class="font-semibold">'.number_format($rows).'</span></li>')->implode('')
                        .'</ul>'
                    )),
                Action::make('erase')
                    ->label('حذف داده‌ها')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(fn (SampleBatch $record): string => "حذف داده‌های {$record->code}")
                    ->modalDescription(fn (SampleBatch $record): string => 'همه‌ی '.number_format($record->records()->count()).' رکوردی که این دسته ساخته (کلاس، حساب‌ها، جلسه‌ها، …) پاک می‌شود. آنچه پیش از آن وجود داشت تغییری نمی‌کند.')
                    ->modalSubmitActionLabel('پاک کن')
                    ->action(function (SampleBatch $record, SampleBatches $batches): void {
                        try {
                            $count = $batches->delete($record);
                        } catch (RuntimeException $exception) {
                            report($exception);
                            Notification::make()->danger()->title('داده‌ها پاک نشد')->body('چیزی که بعداً بیرون از این دسته ساخته شده هنوز به این داده‌ها وابسته است (مثلاً کلاسی واقعی در همین دوره). اول آن را بردارید.')->persistent()->send();

                            return;
                        }

                        Notification::make()->success()->title('داده‌ها پاک شد')->body(number_format($count).' رکورد پاک شد.')->send();
                    }),
            ]);
    }
}
