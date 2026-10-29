<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SMS driver
    |--------------------------------------------------------------------------
    |
    | "log" writes messages to the Laravel log (development). A real provider
    | (Kavenegar, Ghasedak, ...) is added as another App\Sms\SmsSender driver.
    |
    */

    'driver' => env('SMS_DRIVER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Log channel
    |--------------------------------------------------------------------------
    |
    | Where the "log" driver writes: the "sms" channel is storage/logs/sms.log,
    | so login codes and notices are easy to find while developing.
    |
    */

    'log_channel' => env('SMS_LOG_CHANNEL', 'sms'),

];
