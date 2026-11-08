<?php

namespace Modules\Class\Services\Online;

/**
 * Adobe Connect answered with a status other than «ok», or couldn't be
 * reached (ADR 0073). `status` and `subcode` are Adobe's, e.g. «no-access»
 * / «no-login», «invalid» / «duplicate», «no-data».
 */
class AdobeConnectError extends OnlineClassException
{
    public function __construct(public readonly string $status, public readonly ?string $subcode, string $message)
    {
        parent::__construct($message);
    }
}
