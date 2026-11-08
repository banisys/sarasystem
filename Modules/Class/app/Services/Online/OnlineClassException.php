<?php

namespace Modules\Class\Services\Online;

use RuntimeException;

/**
 * Entering an online class failed or isn't allowed (ADR 0073); the message
 * is shown to the person as is.
 */
class OnlineClassException extends RuntimeException {}
