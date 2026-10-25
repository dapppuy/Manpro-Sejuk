<?php

namespace App\Services\Midtrans;

use RuntimeException;

/**
 * A Midtrans call failed. The message is safe to show to customers.
 */
class MidtransException extends RuntimeException {}
