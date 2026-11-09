<?php

namespace Modules\Class\Services\Online;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Class\Models\OnlineServer;

/**
 * Skyroom's JSON API. The encrypted server password holds its API key.
 * Never report raw responses or transport exceptions: their URLs carry the key.
 */
class SkyroomApi
{
    public function __construct(private OnlineServer $server) {}

    /**
     * @param  array<string, mixed>  $params
     *
     * @throws OnlineClassException
     */
    public function call(string $action, array $params = []): mixed
    {
        if (blank($this->server->password)) {
            throw new OnlineClassException('کلید API سرور Skyroom وارد نشده است.');
        }

        try {
            $response = Http::acceptJson()
                ->connectTimeout(5)
                ->timeout(15)
                ->withOptions(['allow_redirects' => false])
                ->post($this->server->baseUrl().'/'.rawurlencode($this->server->password), [
                    'action' => $action,
                    'params' => $params === [] ? (object) [] : $params,
                ]);
        } catch (ConnectionException) {
            Log::warning('Skyroom unreachable', ['server' => $this->server->getKey(), 'action' => $action]);

            throw new OnlineClassException('سرور Skyroom در دسترس نیست.');
        }

        if (! $response->successful()) {
            if ($response->status() === 503) {
                throw new OnlineClassException('سرویس Skyroom موقتاً در دسترس نیست یا محدودیت تعداد درخواست‌ها پر شده است؛ کمی بعد دوباره تلاش کنید.');
            }

            throw new OnlineClassException('سرور Skyroom پاسخ درستی نداد (HTTP '.$response->status().').');
        }

        $data = $response->json();

        if (! is_array($data) || ! is_bool($data['ok'] ?? null)) {
            throw new OnlineClassException('پاسخ سرور Skyroom خوانا نبود؛ نشانی وب‌سرویس را بررسی کنید.');
        }

        if (! $data['ok']) {
            $code = is_int($data['error_code'] ?? null) ? $data['error_code'] : null;
            Log::warning('Skyroom refused', ['server' => $this->server->getKey(), 'action' => $action, 'code' => $code]);

            throw new OnlineClassException(match ($code) {
                10 => 'وب‌سرویس Skyroom غیرفعال است.',
                11 => 'کلید API سرور Skyroom معتبر نیست.',
                12 => 'دسترسی این درخواست در Skyroom مجاز نیست.',
                13 => 'پارامترهای درخواست Skyroom معتبر نیست؛ تنظیمات سرور را بررسی کنید.',
                15 => 'اتاق یا داده‌ی درخواستی در Skyroom پیدا نشد.',
                default => 'Skyroom درخواست «'.$action.'» را نپذیرفت؛ کلید API، ظرفیت و وضعیت سرویس را بررسی کنید.',
            });
        }

        if (! array_key_exists('result', $data)) {
            throw new OnlineClassException('پاسخ سرور Skyroom نتیجه ندارد.');
        }

        return $data['result'];
    }
}
