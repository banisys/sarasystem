<?php

namespace App\Features;

use RuntimeException;

/**
 * A module can't be switched on or off, e.g. because another one needs it.
 */
class ModuleChangeRefused extends RuntimeException {}
