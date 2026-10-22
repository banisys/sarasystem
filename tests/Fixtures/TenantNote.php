<?php

namespace Tests\Fixtures;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Minimal tenant-owned model for testing BelongsToTenant without depending
 * on a real domain model.
 */
#[Fillable(['title', 'tenant_id'])]
class TenantNote extends Model
{
    use BelongsToTenant;

    protected $table = 'test_tenant_notes';

    public static function createTable(): void
    {
        Schema::create('test_tenant_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('title');
            $table->timestamps();
        });
    }
}
