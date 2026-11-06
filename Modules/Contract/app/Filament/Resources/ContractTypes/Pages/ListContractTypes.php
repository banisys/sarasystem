<?php

namespace Modules\Contract\Filament\Resources\ContractTypes\Pages;

use App\Filament\Concerns\HasPageHelp;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Contract\Filament\Resources\ContractTypes\ContractTypeResource;

class ListContractTypes extends ListRecords
{
    use HasPageHelp;

    protected static string $resource = ContractTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->modalWidth('5xl'),
        ];
    }

    protected function pageHelpKey(): string
    {
        return 'contract.contract-types';
    }

    protected function defaultPageHelp(): string
    {
        $learner = config('sarasystem.learner_label');
        $tenant = config('sarasystem.tenant_label');

        return <<<TEXT
        مفهوم قرارداد
        قرارداد توافقی رسمی میان سازمان و یک شخص است که شرایط همکاری یا آموزش او را روشن می‌کند: از چه تاریخی تا چه تاریخی، با چه مبلغی و با چه تعهدهایی. قرارداد کار یک کارمند و قرارداد دوره‌ی آموزشی یک {$learner} دو نمونه‌ی آن هستند.

        کاربرد قرارداد در این سامانه
        در این سامانه با قرارداد، توافق سازمان با کارمندان و {$learner}ان نوشته، امضا و پیگیری می‌شود. قرارداد را می‌توان برای عضوی از یک {$tenant} در «قراردادها → قراردادها» تنظیم کرد، یا در یکی از گام‌های فرایند استخدام و پذیرش آماده کرد تا پس از پذیرفته شدن شخص صادر شود. شخص قرارداد صادرشده را در پنل خودش می‌بیند و اگر روش امضا اجازه دهد، با کد پیامکی امضا می‌کند؛ قرارداد کاغذی هم با بارگذاری تصویر نسخه‌ی امضاشده ثبت می‌شود.

        هدف از ساختن قرارداد
        • روشن بودن شرایط برای هر دو طرف، پیش از شروع همکاری یا آموزش.
        • داشتن سندی ثابت: متن قرارداد هنگام صدور ثابت می‌شود و تغییرهای بعدی در نوع قرارداد یا اطلاعات شخص آن را عوض نمی‌کند.
        • ثبت امضا: معلوم است قرارداد چه زمانی و به چه روشی امضا شده است.
        • پیگیری: هر قرارداد شماره و وضعیت دارد و قراردادهای رو به پایان یادآوری می‌شوند تا به‌موقع تمدید شوند. برای تغییر قرارداد امضاشده هم الحاقیه صادر می‌شود.

        این صفحه چه کاری می‌کند؟
        «نوع قرارداد» خودِ قرارداد نیست، بلکه الگوی آن است و یک بار برای همه‌ی سازمان تعریف می‌شود. در هر نوع، عنوان، متن قرارداد، روش امضا و پیشوند شماره را تعیین می‌کنید. در متن با تایپ «{{» مقدارهایی مانند نام شخص را درج می‌کنید تا در هر قرارداد خودکار پر شوند. از منوی هر ردیف، «فیلدهای قرارداد» مشخص می‌کند هنگام تنظیم قرارداد چه اطلاعاتی پرسیده شود.

        مبلغ قرارداد فقط در متن آن می‌آید؛ اقساط و پرداخت‌ها در این بخش ثبت نمی‌شوند.
        TEXT;
    }
}
