<?php

namespace App\Exceptions;

use Exception;

/**
 * An order action no longer fits the order's current status. The message is safe to show to users.
 */
class InvalidOrderTransition extends Exception {}
