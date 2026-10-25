<?php

namespace Modules\Employee\Filament\Actions;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Modules\Employee\Models\JobApplication;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Downloads an application's résumé from the private disk (ADR 0014).
 */
class DownloadResumeAction
{
    public static function make(): Action
    {
        return Action::make('downloadResume')
            ->label('دریافت رزومه')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->visible(fn (JobApplication $record): bool => $record->hasMedia(JobApplication::RESUME_COLLECTION))
            ->action(function (JobApplication $record): BinaryFileResponse {
                $media = $record->getFirstMedia(JobApplication::RESUME_COLLECTION);

                return response()->download($media->getPath(), "resume-{$record->national_code}.pdf");
            });
    }
}
