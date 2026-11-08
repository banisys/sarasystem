<?php

namespace Modules\Class\Enums;

use Filament\Support\Contracts\HasLabel;
use Modules\Class\Services\Online\AdobeConnectDriver;
use Modules\Class\Services\Online\LinkDriver;
use Modules\Class\Services\Online\OnlinePlatformDriver;

/**
 * Where an online class meets (ADR 0073). «لینک» is any meeting address
 * (Google Meet, Skyroom, …) people are sent to as it is; the others are
 * platforms the system talks to on a server: it makes the room and the
 * people's accounts and signs them in.
 */
enum OnlinePlatform: string implements HasLabel
{
    case Link = 'link';

    case AdobeConnect = 'adobe_connect';

    public function getLabel(): string
    {
        return match ($this) {
            self::Link => 'لینک',
            self::AdobeConnect => 'Adobe Connect',
        };
    }

    public function needsServer(): bool
    {
        return $this !== self::Link;
    }

    /**
     * Platforms an installation adds servers of.
     *
     * @return array<string, string>
     */
    public static function serverOptions(): array
    {
        return collect(self::cases())
            ->filter(fn (self $platform): bool => $platform->needsServer())
            ->mapWithKeys(fn (self $platform): array => [$platform->value => $platform->getLabel()])
            ->all();
    }

    public function driver(): OnlinePlatformDriver
    {
        return match ($this) {
            self::Link => app(LinkDriver::class),
            self::AdobeConnect => app(AdobeConnectDriver::class),
        };
    }
}
