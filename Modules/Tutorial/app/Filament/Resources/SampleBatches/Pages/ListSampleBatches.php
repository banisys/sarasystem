<?php

namespace Modules\Tutorial\Filament\Resources\SampleBatches\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Modules\Class\Services\ClassException;
use Modules\Tutorial\Filament\Resources\SampleBatches\SampleBatchResource;
use Modules\Tutorial\Filament\Resources\SampleBatches\Schemas\ClassSampleForm;
use Modules\Tutorial\Services\ClassSampleGenerator;

class ListSampleBatches extends ListRecords
{
    protected static string $resource = SampleBatchResource::class;

    protected ?string $subheading = 'کلاسی با همه‌ی آنچه دور آن است (اعضا، برنامه، جلسه‌ها، حضور و غیاب، تکلیف و نمره) برای نشان دادن سامانه به مشتری بسازید. هر بار ساخت یک کد دارد و با «حذف داده‌ها» همه‌ی آنچه ساخته پاک می‌شود.';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')
                ->label('ساخت داده‌ی نمونه')
                ->icon(Heroicon::OutlinedSparkles)
                ->modalHeading('ساخت داده‌ی نمونه‌ی کلاس')
                ->modalDescription('همه‌ی خانه‌ها اختیاری‌اند؛ خالی‌ها با مقدار پیش‌فرض ساخته می‌شوند.')
                ->modalWidth(Width::FiveExtraLarge)
                ->modalSubmitActionLabel('بساز')
                ->fillForm(fn (): array => ClassSampleForm::defaults())
                ->schema(ClassSampleForm::components())
                ->action(function (array $data, Action $action, ClassSampleGenerator $generator): void {
                    try {
                        $batch = $generator->generate($data, auth()->user());
                    } catch (ClassException $exception) {
                        Notification::make()->danger()->title('داده‌ی نمونه ساخته نشد')->body($exception->getMessage())->send();
                        $action->halt();

                        return;
                    }

                    Notification::make()->success()
                        ->title("«{$batch->title}» ساخته شد")
                        ->body(number_format($batch->records()->count())." رکورد با کد {$batch->code}.")
                        ->send();
                }),
        ];
    }
}
