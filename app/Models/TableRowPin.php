<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['tenant_id', 'user_id', 'table_key', 'record_key'])]
class TableRowPin extends Model
{
    use BelongsToTenant;

    public $timestamps = false;
}
