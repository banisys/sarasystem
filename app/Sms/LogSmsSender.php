<?php

namespace App\Sms;

use Illuminate\Support\Facades\Log;

/**
 * Development driver: writes messages to the Laravel log instead of sending them.
 */
class LogSmsSender implements SmsSender
{
    public function send(string $mobile, string $message): void
    {
        Log::info("[sms] to {$mobile}: {$message}");
    }
}
