<?php

namespace App\Workflow;

use RuntimeException;

/**
 * A workflow action that can't be done (not assigned, already moved on, a
 * business rule of the subject); the message is shown to the user.
 */
class WorkflowActionDenied extends RuntimeException {}
