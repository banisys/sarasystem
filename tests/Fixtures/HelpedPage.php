<?php

namespace Tests\Fixtures;

use App\Filament\Concerns\HasPageHelp;
use Filament\Pages\Page;

/**
 * A minimal panel page with help, so core tests need no module.
 */
class HelpedPage extends Page
{
    use HasPageHelp;

    public const string DEFAULT_HELP = "خط اول راهنما\nخط دوم راهنما";

    protected static ?string $title = 'صفحه‌ی نمونه';

    protected static bool $shouldRegisterNavigation = false;

    protected function pageHelpKey(): string
    {
        return 'tests.helped-page';
    }

    protected function defaultPageHelp(): string
    {
        return self::DEFAULT_HELP;
    }
}
