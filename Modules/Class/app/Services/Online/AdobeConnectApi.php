<?php

namespace Modules\Class\Services\Online;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Class\Models\OnlineServer;
use SimpleXMLElement;

/**
 * Adobe Connect's XML web services (`/api/xml?action=…`) on one server
 * (ADR 0073). A session comes from `common-info` (its `<cookie>`) and
 * becomes someone's by `login`; then every call carries it as `session=`.
 * The administrator's session is kept in the cache for a few minutes and
 * made again when the server says it is no longer logged in.
 */
class AdobeConnectApi
{
    private const ADMIN_SESSION_MINUTES = 10;

    public function __construct(private readonly OnlineServer $server) {}

    /**
     * A call as the server's administrator.
     *
     * @param  array<string, scalar|null>  $params
     *
     * @throws AdobeConnectError
     */
    public function call(string $action, array $params = []): SimpleXMLElement
    {
        try {
            return $this->request($action, [...$params, 'session' => $this->adminSession()]);
        } catch (AdobeConnectError $error) {
            if ($error->status !== 'no-access' || $error->subcode !== 'no-login') {
                throw $error;
            }

            Cache::forget($this->cacheKey());

            return $this->request($action, [...$params, 'session' => $this->adminSession()]);
        }
    }

    /**
     * A session logged in as the account, to send its holder into a room.
     *
     * @throws AdobeConnectError
     */
    public function login(string $login, string $password): string
    {
        $session = (string) $this->request('common-info')->common->cookie;

        if ($session === '') {
            throw new AdobeConnectError('no-session', null, 'Adobe Connect نشستی نداد.');
        }

        $this->request('login', ['login' => $login, 'password' => $password, 'session' => $session]);

        return $session;
    }

    /**
     * @throws AdobeConnectError
     */
    private function adminSession(): string
    {
        return Cache::remember(
            $this->cacheKey(),
            now()->addMinutes(self::ADMIN_SESSION_MINUTES),
            fn (): string => $this->login((string) $this->server->username, (string) $this->server->password),
        );
    }

    private function cacheKey(): string
    {
        // The host and login are part of the key, so editing the server
        // doesn't reuse a session of the old one.
        return 'class-online:adobe:'.$this->server->getKey().':'.md5($this->server->baseUrl().'|'.$this->server->username.'|'.$this->server->password);
    }

    /**
     * @param  array<string, scalar|null>  $params
     *
     * @throws AdobeConnectError
     */
    private function request(string $action, array $params = []): SimpleXMLElement
    {
        try {
            $response = Http::connectTimeout(5)
                ->timeout(15)
                ->retry(2, 500, fn ($exception): bool => $exception instanceof ConnectionException, throw: false)
                ->withOptions(['verify' => (bool) $this->server->setting('verify_ssl', true)])
                ->get($this->server->baseUrl().'/api/xml', ['action' => $action, ...array_filter($params, fn ($value): bool => $value !== null)]);
        } catch (ConnectionException $exception) {
            Log::warning('Adobe Connect unreachable', ['server' => $this->server->getKey(), 'action' => $action, 'error' => $exception->getMessage()]);

            throw new AdobeConnectError('unreachable', null, 'سرور Adobe Connect در دسترس نیست.');
        }

        if (! $response->successful()) {
            throw new AdobeConnectError('http-'.$response->status(), null, 'سرور Adobe Connect پاسخ درستی نداد (HTTP '.$response->status().').');
        }

        $xml = rescue(fn () => new SimpleXMLElement($response->body()), null, report: false);

        if (! $xml instanceof SimpleXMLElement || ! isset($xml->status)) {
            throw new AdobeConnectError('not-xml', null, 'پاسخ سرور Adobe Connect خوانا نبود؛ نشانی سرور را بررسی کنید.');
        }

        $status = (string) $xml->status['code'];

        if ($status !== 'ok') {
            $subcode = (string) ($xml->status['subcode'] ?? '') ?: (string) ($xml->status->invalid['subcode'] ?? '') ?: null;
            Log::warning('Adobe Connect refused', ['server' => $this->server->getKey(), 'action' => $action, 'status' => $status, 'subcode' => $subcode, 'field' => (string) ($xml->status->invalid['field'] ?? '')]);

            throw new AdobeConnectError($status, $subcode, "Adobe Connect درخواست «{$action}» را نپذیرفت ({$status}".($subcode ? "، {$subcode}" : '').').');
        }

        return $xml;
    }
}
