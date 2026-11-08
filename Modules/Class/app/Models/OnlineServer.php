<?php

namespace Modules\Class\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Class\Database\Factories\OnlineServerFactory;
use Modules\Class\Enums\OnlinePlatform;

/**
 * A server of an online-class platform, e.g. the organization's Adobe
 * Connect (ADR 0073). Shared by every tenant: a class picks one. The
 * administrator's password is kept encrypted.
 *
 * Settings (Adobe Connect): `folder_id` (where rooms are made; empty is the
 * server's «Shared Meetings»), `login_prefix` (people's logins are
 * «{prefix}{user id}@{host}»), `verify_ssl`.
 */
#[Fillable(['platform', 'title', 'host', 'username', 'password', 'settings', 'is_active'])]
#[Hidden(['password'])]
class OnlineServer extends Model
{
    /** @use HasFactory<OnlineServerFactory> */
    use HasFactory;

    protected static function newFactory(): OnlineServerFactory
    {
        return OnlineServerFactory::new();
    }

    /**
     * @param  Builder<OnlineServer>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        $value = $this->settings[$key] ?? null;

        return $value === null || $value === '' ? $default : $value;
    }

    /**
     * The host without a trailing slash, e.g. «https://adobe.example.com».
     */
    public function baseUrl(): string
    {
        $host = trim($this->host);

        return rtrim(parse_url($host, PHP_URL_SCHEME) ? $host : 'https://'.$host, '/');
    }

    /**
     * «Adobe Connect — سرور اول».
     */
    public function displayName(): string
    {
        return $this->platform->getLabel().' — '.$this->title;
    }

    /**
     * @return HasMany<ClassOnlineRoom, $this>
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(ClassOnlineRoom::class, 'server_id');
    }

    protected function casts(): array
    {
        return [
            'platform' => OnlinePlatform::class,
            'password' => 'encrypted',
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
