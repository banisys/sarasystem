<?php

namespace Modules\Employee\Services;

use RuntimeException;

/**
 * A person can't be made an employee (not allowed, already an employee,
 * a taken personnel code, …); the message is shown to the user.
 */
class EmployeeException extends RuntimeException {}
