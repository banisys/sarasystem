<?php

namespace Tests\Fakes;

use App\Sms\SmsSender;

class FakeSmsSender implements SmsSender
{
    /** @var list<array{mobile: string, message: string}> */
    public array $sent = [];

    public function send(string $mobile, string $message): void
    {
        $this->sent[] = ['mobile' => $mobile, 'message' => $message];
    }

    /**
     * The digits of the last login code sent to the mobile.
     */
    public function lastCodeFor(string $mobile): ?string
    {
        foreach (array_reverse($this->sent) as $sms) {
            if ($sms['mobile'] === $mobile && preg_match('/(\d{6})/', $sms['message'], $match)) {
                return $match[1];
            }
        }

        return null;
    }
}
