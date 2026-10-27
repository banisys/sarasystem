<?php

namespace App\Models\Concerns;

use App\Models\FormCustomization;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * For records that can have their own setup of a customizable form, e.g. a
 * job position (ADR 0017). Their setup goes when they are deleted.
 */
trait HasFormCustomizations
{
    public static function bootHasFormCustomizations(): void
    {
        static::deleted(fn (self $model) => $model->formCustomizations()->withoutGlobalScopes()->delete());
    }

    /**
     * @return MorphMany<FormCustomization, $this>
     */
    public function formCustomizations(): MorphMany
    {
        return $this->morphMany(FormCustomization::class, 'owner');
    }
}
