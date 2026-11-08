<?php

namespace Modules\Class\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A person's account on an online-class server, made the first time they
 * enter a room there (ADR 0073); `external_id` is the platform's id, e.g.
 * Adobe Connect's principal-id. Its password is not kept: it is worked out
 * from the app key.
 */
#[Fillable(['server_id', 'user_id', 'external_id'])]
class OnlineServerAccount extends Model {}
