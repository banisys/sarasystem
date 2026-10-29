<?php

namespace Modules\School\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\School\Enums\ScoreScale;

/**
 * A stage of school («دوره»), e.g. «دبستان» or «متوسطه دوم» (ADR 0031),
 * with how its scores are given. Defined in the primary tenant.
 */
#[Fillable(['tenant_id', 'title', 'scale', 'sort'])]
class Stage extends Model
{
    use BelongsToTenant;

    protected $table = 'school_stages';

    /**
     * @return Builder<Stage>
     */
    public static function definedStages(): Builder
    {
        return static::withoutTenantScope()->where('tenant_id', Tenant::primary()?->getKey())->orderBy('sort')->orderBy('id');
    }

    /**
     * @return HasMany<Grade, $this>
     */
    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class)->withoutGlobalScopes()->orderBy('sort')->orderBy('id');
    }

    protected function casts(): array
    {
        return [
            'scale' => ScoreScale::class,
        ];
    }
}
