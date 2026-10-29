<?php

namespace Modules\School\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A major («رشته»), e.g. «ریاضی و فیزیک», for the grades that have majors
 * (ADR 0031). Defined in the primary tenant.
 */
#[Fillable(['tenant_id', 'title', 'sort'])]
class Major extends Model
{
    use BelongsToTenant;

    protected $table = 'school_majors';

    /**
     * @return Builder<Major>
     */
    public static function definedMajors(): Builder
    {
        return static::withoutTenantScope()->where('tenant_id', Tenant::primary()?->getKey())->orderBy('sort')->orderBy('id');
    }

    /**
     * @return array<int, string>
     */
    public static function options(): array
    {
        return static::definedMajors()->pluck('title', 'id')->all();
    }
}
