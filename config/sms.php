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

];
