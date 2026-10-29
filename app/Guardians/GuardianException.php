<?php

namespace App\Guardians;

use RuntimeException;

/**
 * A guardian rule was broken; the message is shown to the user as is.
 */
class GuardianException extends RuntimeException {}
