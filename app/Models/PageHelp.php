<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * The text of a panel page's help as the organization edited it (ADR 0065).
 * One text per page for the whole installation, so it has no tenant. A page
 * without a row shows the default text in its code (HasPageHelp).
 */
#[Fillable(['key', 'body', 'updated_by'])]
class PageHelp extends Model
{
    public const string EDIT_PERMISSION = 'Update:PageHelp';

    public static function textFor(string $key, string $default): string
    {
        return static::query()->where('key', $key)->value('body') ?? $default;
    }

    /**
     * Keeps only a text that differs from the default: emptying the text or
     * writing the default again goes back to following the code.
     */
    public static function saveText(string $key, ?string $body, string $default, ?User $editor): void
    {
        $body = trim((string) $body);

        if ($body === '' || $body === trim($default)) {
            static::query()->where('key', $key)->first()?->delete();

            return;
        }

        static::query()->updateOrCreate(['key' => $key], ['body' => $body, 'updated_by' => $editor?->getKey()]);
    }
}
