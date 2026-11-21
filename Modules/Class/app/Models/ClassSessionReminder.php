<?php

namespace Modules\Class\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Class\Database\Factories\ClassSessionReminderFactory;

#[Fillable(['tenant_id', 'session_id', 'source_session_id', 'author_id', 'body'])]
class ClassSessionReminder extends Model
{
    /** @use HasFactory<ClassSessionReminderFactory> */
    use BelongsToTenant, HasFactory;

    protected static function newFactory(): ClassSessionReminderFactory
    {
        return ClassSessionReminderFactory::new();
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
