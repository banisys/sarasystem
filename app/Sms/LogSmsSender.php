<?php

namespace App\Sms;

use Illuminate\Support\Facades\Log;

/**
 * Development driver: writes messages to a log (storage/logs/sms.log by
 * default, config('sms.log_channel')) instead of sending them.
 */
class LogSmsSender implements SmsSender
{
    public function send(string $mobile, string $message): void
    {
        Log::channel(config('sms.log_channel'))->info("[sms] to {$mobile}: {$message}");
    }
}
