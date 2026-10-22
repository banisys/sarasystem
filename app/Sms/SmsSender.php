<?php

namespace App\Sms;

/**
 * Sends text messages. Drivers live next to this interface and are chosen
 * by config('sms.driver'); see config/sms.php.
 */
interface SmsSender
{
    /**
     * @param  string  $mobile  normalized Iranian mobile (09xxxxxxxxx)
     */
    public function send(string $mobile, string $message): void;
}
